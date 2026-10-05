<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
use App\Support\SmartcardExcelExport;
use App\Support\SmartcardSaldo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Keluar Uang Saku — Muallimaat.
 * Cash Keluar: sccttran (REFFBANK=24, FIDBANK=CASH) + scctcashout (FIDBANK=CASH, Teller/users=cyber_key.users).
 */
class KeluarUangSakuController extends Controller
{
    private const REFFBANK = '24';

    public function index(Request $request): View
    {
        $custid = (int) $request->query('custid', 0);
        $nis = '';
        $nama = '';
        $saldo = 0;
        $siswaRows = collect();
        $cashoutRows = collect();
        $tranRows = collect();

        if ($custid > 0) {
            $siswa = $this->fetchSiswaByCustid($custid);
            if ($siswa) {
                $nis = $siswa->nis;
                $nama = $siswa->nama;
                $saldo = $this->fetchSaldo($custid);
                $siswaRows = collect([(object) [
                    'custid' => $custid,
                    'nis' => $nis,
                    'nama' => $nama,
                    'saldo' => $saldo,
                    'kelas' => $siswa->kelas,
                    'kelompok' => $siswa->kelompok,
                    'jenjang' => $siswa->jenjang,
                ]]);
                $cashoutRows = $this->fetchCashoutRows($custid);
                $tranRows = $this->fetchTranRows($custid);
            } else {
                $custid = 0;
            }
        }

        if ($request->boolean('search')) {
            $siswaRows = $this->fetchSiswaList(trim((string) $request->query('q', '')));
        }

        return view('admin.smartcard.keluar_uang_saku.index', [
            'title' => 'smartCARD',
            'mainTitle' => 'Keluar Uang Saku',
            'dataTitle' => 'PENGELUARAN UANG SAKU',
            'custid' => $custid,
            'nis' => $nis,
            'nama' => $nama,
            'saldo' => $saldo,
            'keterangan' => trim((string) $request->query('keterangan', '')),
            'tanggalManual' => trim((string) $request->query('tanggal_manual', '')),
            'siswaRows' => $siswaRows,
            'cashoutRows' => $cashoutRows,
            'tranRows' => $tranRows,
            'nextSeq' => $this->nextSeqPad(now()),
            'lastTransNo' => session('keluar_uang_saku_transno', ''),
            'searchUrl' => route('admin.smartcard.keluar-uang-saku.siswa-search'),
            'detailUrl' => route('admin.smartcard.keluar-uang-saku.siswa-detail'),
            'exportUrl' => route('admin.smartcard.keluar-uang-saku.export', array_filter([
                'custid' => $custid > 0 ? $custid : null,
                'nama' => $nama !== '' ? $nama : null,
                'q' => trim((string) $request->query('q', '')) ?: null,
            ])),
        ]);
    }

    public function siswaSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $mode = trim((string) $request->query('mode', 'all'));

        if (mb_strlen($q) < 3) {
            return response()->json(['rows' => []]);
        }

        $query = DB::connection('DATA_MYSQL')
            ->table('scctcust as c')
            ->where('c.STCUST', 1);

        $this->applySchoolScope($query, 'c');

        if ($mode === 'nis') {
            $query->where(function ($w) use ($q) {
                $w->where('c.NOCUST', 'like', $q . '%')
                    ->orWhere('c.NUM2ND', 'like', $q . '%')
                    ->orWhere('c.NOCUST', 'like', '%' . $q . '%')
                    ->orWhere('c.NUM2ND', 'like', '%' . $q . '%');
            });
        } elseif ($mode === 'nama') {
            $query->where(function ($w) use ($q) {
                $w->where('c.NMCUST', 'like', $q . '%')
                    ->orWhere('c.NMCUST', 'like', '%' . $q . '%');
            });
        } else {
            $query->where(function ($w) use ($q) {
                $w->where('c.NOCUST', 'like', $q . '%')
                    ->orWhere('c.NUM2ND', 'like', $q . '%')
                    ->orWhere('c.NMCUST', 'like', $q . '%')
                    ->orWhere('c.NOCUST', 'like', '%' . $q . '%')
                    ->orWhere('c.NUM2ND', 'like', '%' . $q . '%')
                    ->orWhere('c.NMCUST', 'like', '%' . $q . '%');
            });
        }

        $found = $query
            ->orderBy('c.NMCUST')
            ->limit(15)
            ->get(['c.CUSTID', 'c.NOCUST', 'c.NUM2ND', 'c.NMCUST', 'c.DESC02', 'c.DESC03', 'c.CODE02']);

        // Saldo hanya di siswaDetail setelah siswa dipilih
        $rows = $found->map(function ($row) {
            $nis = trim((string) ($row->NOCUST ?? ''));
            if ($nis === '') {
                $nis = trim((string) ($row->NUM2ND ?? ''));
            }
            $nama = trim((string) ($row->NMCUST ?? ''));
            $custid = (int) ($row->CUSTID ?? 0);

            return [
                'custid' => $custid,
                'nis' => $nis,
                'nama' => $nama,
                'label' => $nis !== '' && $nama !== '' ? $nis . ' — ' . $nama : ($nis ?: $nama),
                'kelas' => trim((string) ($row->DESC02 ?? '')),
                'kelompok' => trim((string) ($row->DESC03 ?? '')),
                'jenjang' => trim((string) ($row->CODE02 ?? '')),
            ];
        })->values()->all();

        return response()->json(['rows' => $rows]);
    }

    public function siswaDetail(Request $request): JsonResponse
    {
        $custid = (int) $request->query('custid', 0);
        if ($custid <= 0) {
            return response()->json(['ok' => false, 'message' => 'CUSTID tidak valid.'], 422);
        }

        $siswa = $this->fetchSiswaByCustid($custid);
        if (!$siswa || !$this->siswaInScope($custid)) {
            return response()->json(['ok' => false, 'message' => 'Siswa tidak ditemukan.'], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'custid' => $custid,
                'nis' => $siswa->nis,
                'nama' => $siswa->nama,
                'saldo' => $this->fetchSaldo($custid),
                'kelas' => $siswa->kelas,
                'kelompok' => $siswa->kelompok,
                'jenjang' => $siswa->jenjang,
                'cashout_rows' => $this->fetchCashoutRows($custid),
                'tran_rows' => $this->fetchTranRows($custid),
                'next_seq' => $this->nextSeqPad(now()),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'custid' => ['required', 'integer', 'min:1'],
            'nominal' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'tanggal_manual' => ['nullable', 'string', 'max:20'],
        ], [
            'custid.required' => 'NIS harap diisi',
            'custid.min' => 'NIS harap diisi',
            'nominal.required' => 'Nominal masih 0',
            'nominal.min' => 'Nominal masih 0',
        ]);

        $custid = (int) $validated['custid'];
        $nominal = (int) $validated['nominal'];
        $ket = trim((string) ($validated['keterangan'] ?? ''));
        $tglManual = trim((string) ($validated['tanggal_manual'] ?? ''));

        if ($custid <= 0) {
            return redirect()
                ->back()
                ->withInput()
                ->with('smartcard_error', 'NIS harap diisi');
        }

        $siswa = $this->fetchSiswaByCustid($custid);
        if (!$siswa || !$this->siswaInScope($custid)) {
            return redirect()->back()->withInput()->with('smartcard_error', 'Siswa tidak ditemukan.');
        }

        $saldo = $this->fetchSaldo($custid);
        if ($nominal > $saldo) {
            return redirect()
                ->route('admin.smartcard.keluar-uang-saku.index', ['custid' => $custid])
                ->withInput()
                ->with('smartcard_error', 'SALDO Tidak cukup');
        }

        $trxDate = $this->resolveTrxDate($tglManual);
        $transNo = $this->generateTransNo(now());
        $loginUser = $this->currentLoginUsers();

        try {
            DB::connection('DATA_MYSQL')->transaction(function () use (
                $custid,
                $nominal,
                $ket,
                $trxDate,
                $transNo,
                $loginUser
            ) {
                $tranRow = [
                    'CUSTID' => $custid,
                    'NOREFF' => $transNo,
                    'REFFBANK' => self::REFFBANK,
                    'TRXDATE' => $trxDate->format('Y-m-d H:i:s'),
                    'KDCHANNEL' => 11,
                    'DEBET' => $nominal,
                    'KREDIT' => 0,
                    'METODE' => 'FROM SALDO',
                    'FIDBANK' => 'CASH',
                ];
                if ($this->hasColumn('sccttran', 'TRANSNO')) {
                    $tranRow['TRANSNO'] = $transNo;
                }
                if ($this->hasColumn('sccttran', 'Keterangan')) {
                    $tranRow['Keterangan'] = $ket !== '' ? $ket : null;
                }
                if ($this->hasColumn('sccttran', 'HELPDESK')) {
                    $tranRow['HELPDESK'] = 'User: ' . $loginUser;
                }
                DB::connection('DATA_MYSQL')->table('sccttran')->insert($tranRow);

                $cashRow = [
                    'CUSTID' => $custid,
                    'BILLAM' => $nominal,
                    'TanggalKeluar' => $trxDate->format('Y-m-d H:i:s'),
                    'Teller' => $loginUser,
                    'TRANSNO' => $transNo,
                    'FIDBANK' => 'CASH',
                ];
                if ($this->hasColumn('scctcashout', 'users')) {
                    $cashRow['users'] = $loginUser;
                }
                if ($this->hasColumn('scctcashout', 'Keterangan')) {
                    $cashRow['Keterangan'] = $ket !== '' ? $ket : null;
                }
                DB::connection('DATA_MYSQL')->table('scctcashout')->insert($cashRow);
            });
        } catch (\Throwable $e) {
            Log::error('KeluarUangSaku store failed', ['message' => $e->getMessage()]);
            report($e);

            return redirect()
                ->route('admin.smartcard.keluar-uang-saku.index', ['custid' => $custid])
                ->withInput()
                ->with('smartcard_error', 'Gagal cash keluar: ' . $e->getMessage());
        }

        return redirect()
            ->route('admin.smartcard.keluar-uang-saku.index', ['custid' => $custid])
            ->with('smartcard_success', 'Tagihan sudah dibayar secara manual. No: ' . $transNo)
            ->with('keluar_uang_saku_transno', $transNo);
    }

    public function cetak(Request $request): Response|RedirectResponse
    {
        $custid = (int) $request->input('custid', 0);
        if ($custid <= 0) {
            return redirect()->back()->with('smartcard_error', 'Pilih siswa terlebih dahulu.');
        }

        $siswa = $this->fetchSiswaByCustid($custid);
        if (!$siswa) {
            return redirect()->back()->with('smartcard_error', 'Siswa tidak ditemukan.');
        }

        $saldo = $this->fetchSaldo($custid);
        $tranRows = $this->fetchTranRows($custid, 200);
        $totalMasuk = $tranRows->sum(fn ($r) => (int) ($r->kredit ?? 0));
        $totalKeluar = $tranRows->sum(fn ($r) => (int) ($r->debet ?? 0));

        $pdf = Pdf::loadView('admin.smartcard.keluar_uang_saku.cetak-pdf', [
            'siswa' => $siswa,
            'saldo' => $saldo,
            'tranRows' => $tranRows,
            'totalMasuk' => $totalMasuk,
            'totalKeluar' => $totalKeluar,
            'teller' => $this->currentLoginUsers(),
            'printedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('transaksi-siswa-' . ($siswa->nis ?: $custid) . '.pdf');
    }

    public function export(Request $request): StreamedResponse|RedirectResponse
    {
        $custid = (int) $request->query('custid', 0);
        $namaFilter = trim((string) $request->query('nama', $request->query('q', '')));

        if ($custid <= 0 && $namaFilter === '') {
            return redirect()
                ->route('admin.smartcard.keluar-uang-saku.index')
                ->with('smartcard_error', 'Pilih siswa atau cari nama terlebih dahulu untuk export.');
        }

        $query = DB::connection('DATA_MYSQL')
            ->table('scctcashout as co')
            ->join('scctcust as c', 'co.CUSTID', '=', 'c.CUSTID')
            ->whereRaw('UPPER(TRIM(co.FIDBANK)) = ?', ['CASH']);

        $this->applySchoolScope($query, 'c');

        if ($custid > 0) {
            $query->where('co.CUSTID', $custid);
        } elseif ($namaFilter !== '') {
            $query->where(function ($w) use ($namaFilter) {
                $w->where('c.NMCUST', 'like', '%' . $namaFilter . '%')
                    ->orWhere('c.NOCUST', 'like', '%' . $namaFilter . '%');
            });
        }

        $userParts = [];
        if ($this->hasColumn('scctcashout', 'users')) {
            $userParts[] = "NULLIF(TRIM(co.users), '')";
        }
        $userParts[] = "NULLIF(TRIM(co.Teller), '')";
        $userExpr = 'COALESCE(' . implode(', ', $userParts) . ", '-')";

        $rows = $query
            ->orderByDesc('co.TanggalKeluar')
            ->limit(5000)
            ->get([
                'c.NOCUST as nis',
                'c.NMCUST as nama',
                'co.TRANSNO as no_transaksi',
                'co.TanggalKeluar as tgl_transaksi',
                'co.BILLAM as jumlah',
                DB::raw("{$userExpr} as user_login"),
            ]);

        if ($rows->isEmpty()) {
            return redirect()
                ->route('admin.smartcard.keluar-uang-saku.index', array_filter([
                    'custid' => $custid > 0 ? $custid : null,
                    'search' => $namaFilter !== '' ? 1 : null,
                    'q' => $namaFilter !== '' ? $namaFilter : null,
                ]))
                ->with('smartcard_error', 'Tidak ada data cash keluar untuk diexport.');
        }

        $exportRows = [];
        $no = 1;
        $total = 0;
        $siswaNama = trim((string) ($rows->first()->nama ?? 'siswa'));
        foreach ($rows as $row) {
            $jumlah = (int) ($row->jumlah ?? 0);
            $total += $jumlah;
            $tgl = '';
            if (!empty($row->tgl_transaksi)) {
                try {
                    $tgl = Carbon::parse($row->tgl_transaksi)->format('Y-m-d H:i:s');
                } catch (\Throwable) {
                    $tgl = (string) $row->tgl_transaksi;
                }
            }
            $exportRows[] = [
                $no++,
                $row->nis ?? '',
                $row->nama ?? '',
                $row->no_transaksi ?? '',
                $tgl,
                $jumlah,
                $row->user_login ?? '',
            ];
        }
        $exportRows[] = ['', '', '', '', 'TOTAL', $total, ''];

        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '-', $siswaNama) ?: 'siswa';

        return SmartcardExcelExport::download(
            'keluar-uang-saku-' . $safeName . '-' . date('Ymd-His'),
            ['No', 'NIS', 'Nama', 'No Transaksi', 'Tgl Keluar', 'Jumlah', 'User'],
            $exportRows
        );
    }

    private function fetchSiswaByCustid(int $custid): ?object
    {
        $row = DB::connection('DATA_MYSQL')
            ->table('scctcust')
            ->where('CUSTID', $custid)
            ->first(['CUSTID', 'NOCUST', 'NUM2ND', 'NMCUST', 'DESC02', 'DESC03', 'CODE02']);

        if (!$row) {
            return null;
        }

        $nis = trim((string) ($row->NOCUST ?? ''));
        if ($nis === '') {
            $nis = trim((string) ($row->NUM2ND ?? ''));
        }

        return (object) [
            'custid' => (int) $row->CUSTID,
            'nis' => $nis,
            'nama' => trim((string) ($row->NMCUST ?? '')),
            'kelas' => trim((string) ($row->DESC02 ?? '')),
            'kelompok' => trim((string) ($row->DESC03 ?? '')),
            'jenjang' => trim((string) ($row->CODE02 ?? '')),
        ];
    }

    private function fetchSiswaList(string $q): Collection
    {
        if (!SmartcardSaldo::hasView()) {
            return collect();
        }

        try {
            $view = SmartcardSaldo::VIEW;
            $query = DB::connection('DATA_MYSQL')->table("{$view} as v");
            $this->applySchoolScope($query, 'v');

            if ($q !== '') {
                $query->where(function ($w) use ($q) {
                    $w->where('v.NOCUST', 'like', '%' . $q . '%')
                        ->orWhere('v.NUM2ND', 'like', '%' . $q . '%')
                        ->orWhereRaw('LOWER(v.NMCUST) LIKE ?', ['%' . mb_strtolower($q) . '%']);
                });
            }

            return $query
                ->orderBy('v.CUSTID')
                ->limit(100)
                ->get([
                    'v.CUSTID',
                    'v.NOCUST',
                    'v.NMCUST',
                    'v.SALDO',
                    'v.DESC02',
                    'v.DESC03',
                    'v.CODE02',
                ])
                ->map(function ($row) {
                    return (object) [
                        'custid' => (int) $row->CUSTID,
                        'nis' => trim((string) ($row->NOCUST ?? '')),
                        'nama' => trim((string) ($row->NMCUST ?? '')),
                        'saldo' => (int) ($row->SALDO ?? 0),
                        'kelas' => trim((string) ($row->DESC02 ?? '')),
                        'kelompok' => trim((string) ($row->DESC03 ?? '')),
                        'jenjang' => trim((string) ($row->CODE02 ?? '')),
                    ];
                });
        } catch (\Throwable) {
            return collect();
        }
    }

    private function fetchSaldo(int $custid): int
    {
        return SmartcardSaldo::for($custid);
    }

    private function fetchCashoutRows(int $custid): Collection
    {
        $hasUsers = $this->hasColumn('scctcashout', 'users');
        $cols = ['TanggalKeluar', 'BILLAM', 'Teller', 'TRANSNO'];
        if ($hasUsers) {
            $cols[] = 'users';
        }
        if ($this->hasColumn('scctcashout', 'Keterangan')) {
            $cols[] = 'Keterangan';
        }

        return DB::connection('DATA_MYSQL')
            ->table('scctcashout')
            ->where('CUSTID', $custid)
            ->whereRaw('UPPER(TRIM(FIDBANK)) = ?', ['CASH'])
            ->orderByDesc('TanggalKeluar')
            ->orderByDesc('urut')
            ->limit(50)
            ->get($cols)
            ->map(function ($r) use ($hasUsers) {
                $user = $hasUsers ? trim((string) ($r->users ?? '')) : '';
                if ($user === '') {
                    $user = trim((string) ($r->Teller ?? ''));
                }

                return (object) [
                    'tanggal' => $this->fmtDate($r->TanggalKeluar ?? null),
                    'jumlah' => (int) ($r->BILLAM ?? 0),
                    'teller' => $user,
                    'transno' => trim((string) ($r->TRANSNO ?? '')),
                    'keterangan' => trim((string) ($r->Keterangan ?? '')),
                ];
            });
    }

    private function fetchTranRows(int $custid, int $limit = 80): Collection
    {
        $cols = [
            'TRXDATE as tanggal_raw',
            'FIDBANK as metode',
            'KREDIT as kredit',
            'DEBET as debet',
        ];
        if ($this->hasColumn('sccttran', 'Keterangan')) {
            $cols[] = 'Keterangan as keterangan';
        }
        if ($this->hasColumn('sccttran', 'METODE')) {
            $cols[] = 'METODE as metode_alt';
        }

        return DB::connection('DATA_MYSQL')
            ->table('sccttran')
            ->where('CUSTID', $custid)
            ->orderByDesc('TRXDATE')
            ->orderByDesc('urut')
            ->limit($limit)
            ->get($cols)
            ->map(function ($r) {
                $metode = trim((string) ($r->metode ?? ''));
                if ($metode === '' && isset($r->metode_alt)) {
                    $metode = trim((string) $r->metode_alt);
                }

                return (object) [
                    'tanggal' => $this->fmtDate($r->tanggal_raw ?? null),
                    'metode' => $metode !== '' ? $metode : '-',
                    'kredit' => (int) ($r->kredit ?? 0),
                    'debet' => (int) ($r->debet ?? 0),
                    'keterangan' => trim((string) ($r->keterangan ?? '')) ?: '-',
                ];
            });
    }

    /**
     * Builder: TRANSNO = CurDate() & pad(count, 5).
     * Pakai count+1 agar konsisten modul TAP/Topup (urutan mulai 00001).
     */
    private function generateTransNo(Carbon $today): string
    {
        $prefix = $today->format('Ymd');
        $count = (int) DB::connection('DATA_MYSQL')
            ->table('scctcashout')
            ->whereRaw('SUBSTRING(TRIM(TRANSNO), 1, 8) = ?', [$prefix])
            ->count();

        return $prefix . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }

    private function nextSeqPad(Carbon $today): string
    {
        $prefix = $today->format('Ymd');
        $count = 0;
        try {
            $count = (int) DB::connection('DATA_MYSQL')
                ->table('scctcashout')
                ->whereRaw('SUBSTRING(TRIM(TRANSNO), 1, 8) = ?', [$prefix])
                ->count();
        } catch (\Throwable) {
        }

        return str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }

    private function resolveTrxDate(string $tanggalManual): Carbon
    {
        $raw = trim($tanggalManual);
        if ($raw === '' || $raw === '0000-00-00' || $raw === '00000000') {
            return now();
        }

        try {
            if (preg_match('/^\d{8}$/', $raw)) {
                return Carbon::createFromFormat('Ymd', $raw)->startOfDay();
            }

            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return now();
        }
    }

    private function fmtDate(mixed $value): string
    {
        if ($value === null || $value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return '-';
        }
        try {
            return Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    /** Login username dari cyber_key.users (bukan ket/nama). */
    private function currentLoginUsers(): string
    {
        $user = Auth::user();
        if (!$user) {
            return 'ADMIN';
        }

        $login = trim((string) ($user->users ?? ''));
        if ($login !== '') {
            return $login;
        }

        foreach (['username', 'email'] as $field) {
            $val = trim((string) ($user->{$field} ?? ''));
            if ($val !== '') {
                return $val;
            }
        }

        return 'ADMIN';
    }

    private function applySchoolScope($query, string $alias = 'scctcust'): void
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

    private function siswaInScope(int $custid): bool
    {
        $unit = trim((string) (Auth::user()->unit ?? ''));
        if ($unit === '') {
            return true;
        }

        return DB::connection('DATA_MYSQL')
            ->table('scctcust')
            ->where('CUSTID', $custid)
            ->where(function ($q) use ($unit) {
                $q->whereRaw('TRIM(CAST(CODE01 AS CHAR)) = ?', [$unit])
                    ->orWhereRaw('TRIM(CAST(CODE02 AS CHAR)) = ?', [$unit]);
            })
            ->exists();
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::connection('DATA_MYSQL')->hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }
}
