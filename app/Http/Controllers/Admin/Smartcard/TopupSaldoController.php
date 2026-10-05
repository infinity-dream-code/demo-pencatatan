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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * TOP UP Saldo — Muallimaat: nominal full ke saldo, tanpa admin fee.
 * sccttran: REFFBANK=24, FIDBANK=CASH (atau metode), METODE=TOP UP, 1 baris.
 * sm_topup.users = login cyber_key.users.
 */
class TopupSaldoController extends Controller
{
    private const REFFBANK = '24';

    private const METHODS = ['CASH', 'JURNAL', 'MUAMALAT', 'BSI'];

    public function index(Request $request): View
    {
        $custid = (int) $request->query('custid', 0);
        $nis = '';
        $nama = '';
        $saldo = 0;
        $topupRows = collect();
        $tranRows = collect();
        $siswaRows = collect();

        if ($custid > 0) {
            $siswa = $this->fetchSiswaByCustid($custid);
            if ($siswa) {
                $nis = trim((string) ($siswa->nis ?? ''));
                $nama = trim((string) ($siswa->nama ?? ''));
                $saldo = $this->fetchSaldo($custid);
                $topupRows = $this->fetchTopupRows($custid);
                $tranRows = $this->fetchTranRows($custid);
            } else {
                $custid = 0;
            }
        }

        if ($request->boolean('search')) {
            $siswaRows = $this->fetchSiswaList(
                trim((string) $request->query('q', ''))
            );
        }

        return view('admin.smartcard.topup_saldo.index', [
            'title' => 'smartCARD',
            'mainTitle' => 'TOP UP Saldo',
            'dataTitle' => 'TOPUP CASH SALDO',
            'custid' => $custid,
            'nis' => $nis,
            'nama' => $nama,
            'saldo' => $saldo,
            'metode' => strtoupper(trim((string) $request->query('metode', 'CASH'))) ?: 'CASH',
            'keterangan' => trim((string) $request->query('keterangan', '')),
            'tanggalManual' => trim((string) $request->query('tanggal_manual', '')),
            'methods' => self::METHODS,
            'adminFee' => 0,
            'siswaRows' => $siswaRows,
            'topupRows' => $topupRows,
            'tranRows' => $tranRows,
            'lastTransNo' => session('topup_saldo_transno', ''),
            'searchUrl' => route('admin.smartcard.topup-saldo.siswa-search'),
            'detailUrl' => route('admin.smartcard.topup-saldo.siswa-detail'),
            'exportUrl' => route('admin.smartcard.topup-saldo.export', array_filter([
                'custid' => $custid > 0 ? $custid : null,
                'q' => trim((string) $request->query('q', '')) ?: null,
                'nama' => $nama !== '' ? $nama : null,
            ])),
        ]);
    }

    /** Autocomplete NIS / Nama — minimal 3 karakter. */
    public function siswaSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $mode = trim((string) $request->query('mode', 'all')); // nis | nama | all

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
            ->get(['c.CUSTID', 'c.NOCUST', 'c.NUM2ND', 'c.NMCUST', 'c.DESC02', 'c.DESC03']);

        // Saldo tidak di-load di autocomplete — baru di siswaDetail setelah dipilih
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
                'nis' => trim((string) ($siswa->nis ?? '')),
                'nama' => trim((string) ($siswa->nama ?? '')),
                'saldo' => $this->fetchSaldo($custid),
                'kelas' => trim((string) ($siswa->kelas ?? '')),
                'topup_rows' => $this->fetchTopupRows($custid),
                'tran_rows' => $this->fetchTranRows($custid),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'custid' => ['required', 'integer', 'min:1'],
            'nominal' => ['required', 'integer', 'min:1'],
            'metode' => ['required', 'string', 'max:30'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'tanggal_manual' => ['nullable', 'string', 'max:20'],
        ], [
            'custid.required' => 'NIS dan nama belum diisi',
            'nominal.required' => 'Nominal masih 0',
        ]);

        $custid = (int) $validated['custid'];
        $nominal = (int) $validated['nominal'];
        $metode = strtoupper(trim($validated['metode']));
        if (!in_array($metode, self::METHODS, true)) {
            $metode = 'CASH';
        }
        $ket = trim((string) ($validated['keterangan'] ?? ''));
        $tglManual = trim((string) ($validated['tanggal_manual'] ?? ''));

        if ($nominal <= 0 || $custid <= 0) {
            return redirect()
                ->back()
                ->withInput()
                ->with('smartcard_error', 'Nominal masih 0, atau NIS dan nama belum diisi');
        }

        $siswa = $this->fetchSiswaByCustid($custid);
        if (!$siswa || !$this->siswaInScope($custid)) {
            return redirect()->back()->withInput()->with('smartcard_error', 'Siswa tidak ditemukan.');
        }

        $trxDate = $this->resolveTrxDate($tglManual);
        $transNo = $this->generateTransNo($trxDate);
        $loginUser = $this->currentLoginUsers();

        try {
            DB::connection('DATA_MYSQL')->transaction(function () use (
                $custid,
                $trxDate,
                $transNo,
                $metode,
                $ket,
                $nominal,
                $loginUser
            ) {
                $topupRow = [
                    'CUSTID' => $custid,
                    'NOREFF' => $transNo,
                    'REFFBANK' => self::REFFBANK,
                    'TRXDATE' => $trxDate->format('Y-m-d H:i:s'),
                    'KDCHANNEL' => 11,
                    'DEBET' => 0,
                    'KREDIT' => $nominal,
                    'FIDBANK' => $metode,
                    'TRANSNO' => $transNo,
                    'METODE' => 'TOP UP',
                ];
                if ($this->hasColumn('sccttran', 'Keterangan')) {
                    $topupRow['Keterangan'] = $ket !== '' ? $ket : null;
                }
                if ($this->hasColumn('sccttran', 'HELPDESK')) {
                    $topupRow['HELPDESK'] = 'User: ' . $loginUser;
                }
                if ($this->hasColumn('sccttran', 'MERCHANT')) {
                    $topupRow['MERCHANT'] = $loginUser;
                } elseif ($this->hasColumn('sccttran', 'MERCH')) {
                    $topupRow['MERCH'] = $loginUser;
                }
                DB::connection('DATA_MYSQL')->table('sccttran')->insert($topupRow);

                if (Schema::connection('DATA_MYSQL')->hasTable('sm_topup')) {
                    $topupInsert = [
                        'CUSTID' => $custid,
                        'NOMINAL' => $nominal,
                        'TRXDATE' => $trxDate->format('Y-m-d H:i:s'),
                        'TOPUPNO' => $transNo,
                    ];
                    $userCol = $this->smTopupUserColumn();
                    if ($userCol !== null) {
                        $topupInsert[$userCol] = $loginUser;
                    }
                    DB::connection('DATA_MYSQL')->table('sm_topup')->insert($topupInsert);
                }
            });
        } catch (\Throwable $e) {
            Log::error('TopupSaldo store failed', ['message' => $e->getMessage()]);
            report($e);

            return redirect()
                ->back()
                ->withInput()
                ->with('smartcard_error', 'Gagal top up: ' . $e->getMessage());
        }

        return redirect()
            ->route('admin.smartcard.topup-saldo.index', [
                'custid' => $custid,
                'metode' => $metode,
            ])
            ->with('smartcard_success', 'Top up berhasil. No: ' . $transNo)
            ->with('topup_saldo_transno', $transNo)
            ->with('topup_saldo_custid', $custid);
    }

    public function export(Request $request): StreamedResponse|RedirectResponse
    {
        $custid = (int) $request->query('custid', 0);
        $namaFilter = trim((string) $request->query('nama', $request->query('q', '')));

        if ($custid <= 0 && $namaFilter === '') {
            return redirect()
                ->route('admin.smartcard.topup-saldo.index')
                ->with('smartcard_error', 'Pilih siswa atau cari nama terlebih dahulu untuk export.');
        }

        if (!Schema::connection('DATA_MYSQL')->hasTable('sm_topup')) {
            return redirect()
                ->route('admin.smartcard.topup-saldo.index')
                ->with('smartcard_error', 'Tabel riwayat topup tidak tersedia.');
        }

        $query = DB::connection('DATA_MYSQL')
            ->table('sm_topup as tp')
            ->join('scctcust as c', 'tp.CUSTID', '=', 'c.CUSTID');

        $this->applySchoolScope($query, 'c');

        if ($custid > 0) {
            $query->where('tp.CUSTID', $custid);
        } elseif ($namaFilter !== '') {
            $query->where(function ($w) use ($namaFilter) {
                $w->where('c.NMCUST', 'like', '%' . $namaFilter . '%')
                    ->orWhere('c.NOCUST', 'like', '%' . $namaFilter . '%');
            });
        }

        $userCol = $this->smTopupUserColumn();
        $userSelect = $userCol !== null
            ? "NULLIF(TRIM(tp.`{$userCol}`), '')"
            : 'NULL';

        $rows = $query
            ->orderByDesc('tp.TRXDATE')
            ->limit(5000)
            ->get([
                'c.NOCUST as nis',
                'c.NMCUST as nama',
                'tp.TOPUPNO as no_transaksi',
                'tp.TRXDATE as tgl_transaksi',
                'tp.NOMINAL as topup',
                DB::raw("COALESCE({$userSelect}, '-') as user_login"),
            ]);

        if ($rows->isEmpty()) {
            return redirect()
                ->route('admin.smartcard.topup-saldo.index', array_filter([
                    'custid' => $custid > 0 ? $custid : null,
                    'search' => $namaFilter !== '' ? 1 : null,
                    'q' => $namaFilter !== '' ? $namaFilter : null,
                ]))
                ->with('smartcard_error', 'Tidak ada riwayat topup untuk diexport.');
        }

        $exportRows = [];
        $no = 1;
        $total = 0;
        $siswaNama = trim((string) ($rows->first()->nama ?? 'siswa'));
        foreach ($rows as $row) {
            $nominal = (int) ($row->topup ?? 0);
            $total += $nominal;
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
                $nominal,
                $row->user_login ?? '',
            ];
        }
        $exportRows[] = ['', '', '', '', 'TOTAL', $total, ''];

        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '-', $siswaNama) ?: 'siswa';

        return SmartcardExcelExport::download(
            'riwayat-topup-' . $safeName . '-' . date('Ymd-His'),
            ['No', 'NIS', 'Nama', 'No Transaksi', 'Tgl Transaksi', 'TOPUP', 'User'],
            $exportRows
        );
    }

    public function cetakKuitansi(Request $request): Response|RedirectResponse
    {
        $custid = (int) $request->input('custid', 0);
        $transNo = trim((string) $request->input('transno', ''));

        if ($custid <= 0) {
            return redirect()->back()->with('smartcard_error', 'Pilih siswa terlebih dahulu.');
        }

        $siswa = $this->fetchSiswaByCustid($custid);
        if (!$siswa) {
            return redirect()->back()->with('smartcard_error', 'Siswa tidak ditemukan.');
        }

        $nominal = 0;
        $trxDate = now();
        $metode = 'CASH';
        $ket = '';

        if ($transNo !== '' && Schema::connection('DATA_MYSQL')->hasTable('sm_topup')) {
            $row = DB::connection('DATA_MYSQL')
                ->table('sm_topup')
                ->where('CUSTID', $custid)
                ->whereRaw('TRIM(TOPUPNO) = ?', [$transNo])
                ->orderByDesc('TRXDATE')
                ->first();
            if ($row) {
                $nominal = (int) ($row->NOMINAL ?? 0);
                $trxDate = Carbon::parse($row->TRXDATE ?? now());
            }
        }

        if ($nominal <= 0 && $transNo !== '') {
            $tran = DB::connection('DATA_MYSQL')
                ->table('sccttran')
                ->where('CUSTID', $custid)
                ->where(function ($q) use ($transNo) {
                    $q->whereRaw('TRIM(TRANSNO) = ?', [$transNo])
                        ->orWhereRaw('TRIM(NOREFF) = ?', [$transNo]);
                })
                ->whereRaw("UPPER(TRIM(METODE)) = 'TOP UP'")
                ->orderByDesc('TRXDATE')
                ->first();
            if ($tran) {
                $nominal = (int) ($tran->KREDIT ?? 0);
                $trxDate = Carbon::parse($tran->TRXDATE ?? now());
                $metode = trim((string) ($tran->FIDBANK ?? 'CASH'));
                $ket = trim((string) ($tran->Keterangan ?? ''));
            }
        }

        if ($nominal <= 0) {
            $nominal = (int) $request->input('nominal', 0);
        }

        $pdf = Pdf::loadView('admin.smartcard.topup_saldo.kuitansi-pdf', [
            'sekolahNama' => (string) config('app.name', 'Muallimaat'),
            'nama' => trim((string) ($siswa->nama ?? '')),
            'nis' => trim((string) ($siswa->nis ?? '')),
            'kelas' => trim((string) ($siswa->kelas ?? '')),
            'nominal' => $nominal,
            'fee' => 0,
            'saldoDidapat' => $nominal,
            'transNo' => $transNo !== '' ? $transNo : '-',
            'trxDate' => $trxDate,
            'metode' => $metode,
            'teller' => $this->currentLoginUsers(),
            'note' => $ket,
        ])->setPaper('a5', 'portrait');

        return $pdf->stream('kuitansi-topup-' . ($transNo ?: date('YmdHis')) . '.pdf');
    }

    private function fetchSiswaByCustid(int $custid): ?object
    {
        $row = DB::connection('DATA_MYSQL')
            ->table('scctcust')
            ->where('CUSTID', $custid)
            ->first(['CUSTID', 'NOCUST', 'NUM2ND', 'NMCUST', 'DESC02', 'DESC03', 'CODE01', 'CODE02']);

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
        ];
    }

    private function fetchSiswaList(string $q)
    {
        if (!SmartcardSaldo::hasView()) {
            return collect();
        }

        $view = SmartcardSaldo::VIEW;
        $query = DB::connection('DATA_MYSQL')
            ->table("{$view} as v");

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
    }

    private function fetchSaldo(int $custid): int
    {
        return SmartcardSaldo::for($custid);
    }

    private function fetchTopupRows(int $custid)
    {
        if (!Schema::connection('DATA_MYSQL')->hasTable('sm_topup')) {
            return collect();
        }

        $userCol = $this->smTopupUserColumn();

        $select = [
            'TOPUPNO as no_transaksi',
            'TRXDATE as tgl_transaksi',
            'NOMINAL as topup',
        ];
        if ($userCol !== null) {
            $select[] = DB::raw("`{$userCol}` as user");
        }

        return DB::connection('DATA_MYSQL')
            ->table('sm_topup')
            ->where('CUSTID', $custid)
            ->orderByDesc('TRXDATE')
            ->limit(50)
            ->get($select)
            ->map(function ($r) {
                $r->user = trim((string) ($r->user ?? '')) !== '' ? trim((string) $r->user) : '-';

                return $r;
            });
    }

    private function fetchTranRows(int $custid)
    {
        $cols = ['TRXDATE as tanggal', 'METODE as metode', 'KREDIT as kredit', 'DEBET as debet'];
        if ($this->hasColumn('sccttran', 'Keterangan')) {
            $cols[] = 'Keterangan as keterangan';
        }

        return DB::connection('DATA_MYSQL')
            ->table('sccttran')
            ->where('CUSTID', $custid)
            ->orderByDesc('TRXDATE')
            ->orderByDesc('urut')
            ->limit(80)
            ->get($cols)
            ->map(function ($r) {
                $r->keterangan = $r->keterangan ?? '-';

                return $r;
            });
    }

    private function generateTransNo(Carbon $trxDate): string
    {
        $prefix = $trxDate->format('Ymd');
        $count = 0;

        if (Schema::connection('DATA_MYSQL')->hasTable('sm_topup')) {
            $count = (int) DB::connection('DATA_MYSQL')
                ->table('sm_topup')
                ->whereRaw('SUBSTRING(TRIM(TOPUPNO), 1, 8) = ?', [$prefix])
                ->count();
        } else {
            $count = (int) DB::connection('DATA_MYSQL')
                ->table('sccttran')
                ->whereRaw('SUBSTRING(TRIM(TRANSNO), 1, 8) = ?', [$prefix])
                ->whereRaw("UPPER(TRIM(METODE)) = 'TOP UP'")
                ->count();
        }

        return $prefix . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }

    private function resolveTrxDate(string $tanggalManual): Carbon
    {
        $raw = preg_replace('/\D+/', '', $tanggalManual) ?? '';
        if ($raw === '' || $raw === '00000000' || $tanggalManual === '0000-00-00') {
            return now();
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalManual)) {
                return Carbon::parse($tanggalManual)->setTimeFromTimeString(now()->format('H:i:s'));
            }
            if (strlen($raw) === 8) {
                return Carbon::createFromFormat('Ymd', $raw)->setTimeFromTimeString(now()->format('H:i:s'));
            }
        } catch (\Throwable) {
        }

        return now();
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

    /** Login username dari tabel cyber_key.users (bukan ket/nama). */
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

        foreach (['username', 'email'] as $f) {
            $v = trim((string) ($user->{$f} ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return 'ADMIN';
    }

    private function smTopupUserColumn(): ?string
    {
        foreach (['users', 'user', 'User'] as $col) {
            if ($this->hasColumn('sm_topup', $col)) {
                return $col;
            }
        }

        return null;
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
