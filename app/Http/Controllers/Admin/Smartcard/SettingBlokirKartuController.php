<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SettingBlokirKartuController extends Controller
{
    public function index(Request $request): View
    {
        $isSearch = $request->boolean('search');
        $custid = $isSearch ? (int) $request->query('custid', 0) : 0;

        $nama = '';
        $siswaLabel = $isSearch ? trim((string) $request->query('siswa_search', '')) : '';
        if ($isSearch && $custid > 0) {
            $siswaQuery = DB::connection('DATA_MYSQL')
                ->table('scctcust')
                ->where('CUSTID', $custid);
            $this->applySchoolScope($siswaQuery, 'scctcust');

            $siswa = $siswaQuery->first(['NOCUST', 'NMCUST']);
            if ($siswa) {
                $nama = trim((string) ($siswa->NMCUST ?? ''));
                if ($siswaLabel === '') {
                    $nis = trim((string) ($siswa->NOCUST ?? ''));
                    $siswaLabel = $nis !== '' && $nama !== '' ? $nis . ' - ' . $nama : ($nis !== '' ? $nis : $nama);
                }
            }
        }

        $kartuRows = ($isSearch && $custid > 0) ? $this->fetchCards($custid) : collect();

        $searchError = '';
        if ($isSearch && $custid <= 0) {
            $searchError = 'Pilih siswa (NIS) dari dropdown terlebih dahulu.';
        }

        return view('admin.smartcard.setting_blokir_kartu.index', [
            'title' => 'smartCARD',
            'mainTitle' => 'Setting Blokir Kartu',
            'dataTitle' => 'Setting Blokir Kartu',
            'kartuRows' => $kartuRows,
            'isSearch' => $isSearch,
            'custid' => $custid,
            'nama' => $nama,
            'siswaLabel' => $siswaLabel,
            'searchError' => $searchError,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pid' => ['required', 'string', 'max:50'],
            'custid' => ['required', 'integer', 'min:1'],
            'blokir' => ['required', 'in:0,1'],
        ]);

        $pid = trim((string) $validated['pid']);
        $custid = (int) $validated['custid'];
        $blokir = (int) $validated['blokir'];

        $siswaQuery = DB::connection('DATA_MYSQL')
            ->table('scctcust')
            ->where('CUSTID', $custid);
        $this->applySchoolScope($siswaQuery, 'scctcust');

        if (!$siswaQuery->exists()) {
            return $this->redirectBack($request, $custid)
                ->with('smartcard_error', 'Siswa tidak ditemukan atau di luar unit Anda.');
        }

        $card = DB::connection('DATA_MYSQL')
            ->table('sm_pin')
            ->where('PID', $pid)
            ->where('CUSTID', $custid)
            ->first();

        if (!$card) {
            return $this->redirectBack($request, $custid)
                ->with('smartcard_error', 'Kartu tidak ditemukan.');
        }

        DB::connection('DATA_MYSQL')
            ->table('sm_pin')
            ->where('PID', $pid)
            ->where('CUSTID', $custid)
            ->update(['BLOKIR' => $blokir]);

        $message = $blokir === 1
            ? 'Kartu berhasil diblokir.'
            : 'Blokir kartu berhasil dibuka.';

        return $this->redirectBack($request, $custid)
            ->with('smartcard_success', $message);
    }

    /**
     * @return Collection<int, object>
     */
    private function fetchCards(int $custid): Collection
    {
        return DB::connection('DATA_MYSQL')
            ->table('sm_pin')
            ->where('sm_pin.CUSTID', $custid)
            ->select([
                'sm_pin.PID as no_kartu',
                'sm_pin.PIN as pin',
                'sm_pin.BLOKIR as blokir',
                'sm_pin.CUSTID as custid',
            ])
            ->orderBy('sm_pin.PID')
            ->get();
    }

    private function redirectBack(Request $request, int $custid): RedirectResponse
    {
        $siswaLabel = trim((string) $request->input('siswa_search', $request->query('siswa_search', '')));

        return redirect()->route('admin.smartcard.setting-blokir-kartu.index', array_filter([
            'search' => 1,
            'custid' => $custid,
            'siswa_search' => $siswaLabel !== '' ? $siswaLabel : null,
        ]));
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
