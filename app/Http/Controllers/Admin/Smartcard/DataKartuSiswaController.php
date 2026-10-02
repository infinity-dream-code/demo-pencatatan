<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DataKartuSiswaController extends Controller
{
    private const PER_PAGE = 10;

    public function index(Request $request): View
    {
        $isSearch = $request->boolean('search');
        $custid = $isSearch ? (int) $request->query('custid', 0) : 0;
        $noKartu = $isSearch ? trim((string) $request->query('no_kartu', '')) : '';
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
            'kartuRows' => $this->fetchRows($custid, $noKartu, $isSearch),
            'isSearch' => $isSearch,
            'custid' => $custid,
            'noKartu' => $noKartu,
            'pin' => $pin,
            'nama' => $nama,
            'siswaLabel' => $siswaLabel,
        ]);
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

    private function fetchRows(int $custid, string $noKartu, bool $isSearch): LengthAwarePaginator
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

        return $query
            ->orderByDesc('scctcust.NOCUST')
            ->orderByDesc('sm_pin.PID')
            ->paginate(self::PER_PAGE)
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
