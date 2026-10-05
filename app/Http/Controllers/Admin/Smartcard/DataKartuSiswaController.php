<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
use App\Support\SmartcardExcelExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataKartuSiswaController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100, 200];

    public function index(Request $request): View
    {
        $isSearch = $request->boolean('search');
        $custid = $isSearch ? (int) $request->query('custid', 0) : 0;
        $noKartu = $isSearch ? trim((string) $request->query('no_kartu', '')) : '';
        $perPage = $this->resolvePerPage($request);
        $pin = trim((string) $request->query('pin', '123'));
        if ($pin === '') {
            $pin = '123';
        }

        $nama = '';
        $siswaLabel = $isSearch ? trim((string) $request->query('siswa_search', '')) : '';
        if ($isSearch && $custid > 0) {
            $siswa = DB::connection('DATA_MYSQL')
                ->table('scctcust')
                ->where('CUSTID', $custid)
                ->first(['NOCUST', 'NMCUST']);
            if ($siswa) {
                $nama = trim((string) ($siswa->NMCUST ?? ''));
                if ($siswaLabel === '') {
                    $nis = trim((string) ($siswa->NOCUST ?? ''));
                    $siswaLabel = $nis !== '' && $nama !== '' ? $nis . ' - ' . $nama : ($nis !== '' ? $nis : $nama);
                }
            }
        }

        return view('admin.smartcard.data_kartu_siswa.index', [
            'title' => 'smartCARD',
            'mainTitle' => 'Data Kartu Siswa',
            'dataTitle' => 'Data Kartu Siswa',
            'kartuRows' => $this->fetchRows($custid, $noKartu, $isSearch, $perPage),
            'isSearch' => $isSearch,
            'custid' => $custid,
            'noKartu' => $noKartu,
            'pin' => $pin,
            'nama' => $nama,
            'siswaLabel' => $siswaLabel,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $isSearch = $request->boolean('search');
        $custid = $isSearch ? (int) $request->query('custid', 0) : 0;
        $noKartu = $isSearch ? trim((string) $request->query('no_kartu', '')) : '';

        $rows = $this->baseQuery($custid, $noKartu, $isSearch)
            ->orderByDesc('scctcust.NOCUST')
            ->orderByDesc('sm_pin.PID')
            ->get();

        $exportRows = [];
        $no = 1;
        foreach ($rows as $row) {
            $exportRows[] = [
                $no++,
                $row->nis ?? '',
                $row->nama ?? '',
                $row->no_kartu ?? '',
            ];
        }

        return SmartcardExcelExport::download(
            'data-kartu-siswa-' . date('Ymd-His'),
            ['No', 'NIS', 'Nama', 'No Kartu'],
            $exportRows
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'custid' => ['required', 'integer', 'min:1'],
            'no_kartu' => ['required', 'string', 'max:50'],
            'pin' => ['nullable', 'string', 'max:20'],
        ], [
            'custid.required' => 'Pilih siswa (NIS) terlebih dahulu.',
            'custid.min' => 'Data siswa tidak valid.',
            'no_kartu.required' => 'Nomor kartu wajib diisi.',
        ]);

        $custid = (int) $validated['custid'];
        $noKartu = trim((string) $validated['no_kartu']);
        $pin = trim((string) ($validated['pin'] ?? ''));
        if ($pin === '') {
            $pin = '123';
        }

        $siswaQuery = DB::connection('DATA_MYSQL')
            ->table('scctcust')
            ->where('CUSTID', $custid);
        $this->applySchoolScope($siswaQuery, 'scctcust');

        $siswa = $siswaQuery->first(['CUSTID', 'NOCUST', 'NMCUST']);

        if (!$siswa) {
            return redirect()
                ->back()
                ->withInput()
                ->with('smartcard_error', 'Siswa tidak ditemukan di database.');
        }

        $existsPid = DB::connection('DATA_MYSQL')
            ->table('sm_pin')
            ->where('PID', $noKartu)
            ->exists();

        if ($existsPid) {
            return redirect()
                ->back()
                ->withInput()
                ->with('smartcard_error', 'Nomor kartu sudah digunakan. Setiap nomor kartu harus unik.');
        }

        DB::connection('DATA_MYSQL')->table('sm_pin')->insert([
            'CUSTID' => $custid,
            'PID' => $noKartu,
            'PIN' => $pin,
            'BLOKIR' => 0,
            'urut' => null,
        ]);

        return redirect()
            ->route('admin.smartcard.data-kartu-siswa.index')
            ->with('smartcard_success', 'Data kartu siswa berhasil disimpan.');
    }

    /** Autocomplete siswa (NIS / nama) untuk picker smartCARD. */
    public function siswaSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['rows' => []]);
        }

        $query = DB::connection('DATA_MYSQL')
            ->table('scctcust')
            ->where('STCUST', 1);

        $this->applySchoolScope($query, 'scctcust');

        $query->where(function ($w) use ($q) {
            $w->where('NOCUST', 'like', '%' . $q . '%')
                ->orWhere('NUM2ND', 'like', '%' . $q . '%')
                ->orWhereRaw('LOWER(NMCUST) LIKE ?', ['%' . mb_strtolower($q) . '%']);
        });

        $rows = $query
            ->orderBy('NMCUST')
            ->limit(40)
            ->get(['CUSTID', 'NOCUST', 'NUM2ND', 'NMCUST'])
            ->map(static function ($row) {
                $nis = trim((string) ($row->NOCUST ?? ''));
                if ($nis === '') {
                    $nis = trim((string) ($row->NUM2ND ?? ''));
                }
                $nama = trim((string) ($row->NMCUST ?? ''));
                $label = $nis !== '' && $nama !== '' ? $nis . ' - ' . $nama : ($nis !== '' ? $nis : $nama);

                return [
                    'cid' => (int) ($row->CUSTID ?? 0),
                    'label' => $label,
                    'nocust' => trim((string) ($row->NOCUST ?? '')),
                    'nmcust' => $nama,
                ];
            })
            ->values()
            ->all();

        return response()->json(['rows' => $rows]);
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 10);
        if (!in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            return 10;
        }

        return $perPage;
    }

    private function baseQuery(int $custid, string $noKartu, bool $isSearch)
    {
        $query = DB::connection('DATA_MYSQL')
            ->table('sm_pin')
            ->join('scctcust', 'sm_pin.CUSTID', '=', 'scctcust.CUSTID')
            ->select([
                'scctcust.NOCUST as nis',
                'scctcust.NMCUST as nama',
                'sm_pin.PID as no_kartu',
            ]);

        $this->applySchoolScope($query, 'scctcust');

        if ($isSearch) {
            if ($custid > 0) {
                $query->where('sm_pin.CUSTID', $custid);
            }
            if ($noKartu !== '') {
                $query->where('sm_pin.PID', $noKartu);
            }
        }

        return $query;
    }

    private function fetchRows(int $custid, string $noKartu, bool $isSearch, int $perPage): LengthAwarePaginator
    {
        return $this->baseQuery($custid, $noKartu, $isSearch)
            ->orderByDesc('scctcust.NOCUST')
            ->orderByDesc('sm_pin.PID')
            ->paginate($perPage)
            ->withQueryString();
    }

    private function applySchoolScope($query, string $alias): void
    {
        $unit = trim((string) (Auth::user()->unit ?? ''));
        if ($unit === '') {
            return;
        }

        $query->where(function ($q) use ($unit, $alias) {
            $q->whereRaw('TRIM(CAST(' . $alias . '.CODE01 AS CHAR)) = ?', [$unit])
                ->orWhereRaw('TRIM(CAST(' . $alias . '.CODE02 AS CHAR)) = ?', [$unit]);
        });
    }
}
