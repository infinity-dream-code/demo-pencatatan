<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
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

/**
 * TOP UP Saldo — port builder Al Multazam (TOPUP CASH SALDO).
 * Nominal masuk full ke saldo; di sccttran dipecah TOP UP (nominal-2000) + AdminFee (2000).
 */
class TopupSaldoController extends Controller
{
    private const ADMIN_FEE = 2000;

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
            'adminFee' => self::ADMIN_FEE,
            'siswaRows' => $siswaRows,
            'topupRows' => $topupRows,
            'tranRows' => $tranRows,
            'lastTransNo' => session('topup_saldo_transno', ''),
            'searchUrl' => route('admin.smartcard.topup-saldo.siswa-search'),
            'detailUrl' => route('admin.smartcard.topup-saldo.siswa-detail'),
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
            $metode = 'JURNAL';
        }
        $ket = trim((string) ($validated['keterangan'] ?? ''));
        $tglManual = trim((string) ($validated['tanggal_manual'] ?? ''));

        if ($nominal <= self::ADMIN_FEE) {
            return redirect()
                ->back()
                ->withInput()
                ->with('smartcard_error', 'Nominal harus lebih dari Rp ' . number_format(self::ADMIN_FEE, 0, ',', '.') . ' (biaya admin).');
        }

        if ($custid <= 0) {
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
        $teller = $this->currentUserLabel();
        $topupNet = $nominal - self::ADMIN_FEE;
        $fee = self::ADMIN_FEE;

        try {
            DB::connection('DATA_MYSQL')->transaction(function () use (
                $custid,
                $trxDate,
                $transNo,
                $metode,
                $ket,
                $topupNet,
                $fee,
                $nominal,
                $teller
            ) {
                $base = [
                    'CUSTID' => $custid,
                    'NOREFF' => $transNo,
                    'REFFBANK' => '38',
                    'TRXDATE' => $trxDate->format('Y-m-d H:i:s'),
                    'KDCHANNEL' => 11,
                    'DEBET' => 0,
                    'FIDBANK' => $metode,
                    'TRANSNO' => $transNo,
                ];

                $topupRow = array_merge($base, [
                    'KREDIT' => $topupNet,
                    'METODE' => 'TOP UP',
                ]);
                if ($this->hasColumn('sccttran', 'Keterangan')) {
                    $topupRow['Keterangan'] = $ket !== '' ? $ket : null;
                }
                DB::connection('DATA_MYSQL')->table('sccttran')->insert($topupRow);

                DB::connection('DATA_MYSQL')->table('sccttran')->insert(array_merge($base, [
                    'KREDIT' => $fee,
                    'METODE' => 'AdminFee',
                ]));

                if (Schema::connection('DATA_MYSQL')->hasTable('sm_topup')) {
                    $topupInsert = [
                        'CUSTID' => $custid,
                        'NOMINAL' => $nominal,
                        'TRXDATE' => $trxDate->format('Y-m-d H:i:s'),
                        'TOPUPNO' => $transNo,
                    ];
                    if ($this->hasColumn('sm_topup', 'user')) {
                        $topupInsert['user'] = $teller;
                    } elseif ($this->hasColumn('sm_topup', 'User')) {
                        $topupInsert['User'] = $teller;
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
                $topupNet = (int) ($tran->KREDIT ?? 0);
                $nominal = $topupNet + self::ADMIN_FEE;
                $trxDate = Carbon::parse($tran->TRXDATE ?? now());
                $metode = trim((string) ($tran->FIDBANK ?? 'CASH'));
                $ket = trim((string) ($tran->Keterangan ?? ''));
            }
        }

        if ($nominal <= 0) {
            $nominal = (int) $request->input('nominal', 0);
        }

        $pdf = Pdf::loadView('admin.smartcard.topup_saldo.kuitansi-pdf', [
            'sekolahNama' => 'YPI Al Multazam',
            'nama' => trim((string) ($siswa->nama ?? '')),
            'nis' => trim((string) ($siswa->nis ?? '')),
            'kelas' => trim((string) ($siswa->kelas ?? '')),
            'nominal' => $nominal,
            'fee' => self::ADMIN_FEE,
            'saldoDidapat' => max(0, $nominal - self::ADMIN_FEE),
            'transNo' => $transNo !== '' ? $transNo : '-',
            'trxDate' => $trxDate,
            'metode' => $metode,
            'teller' => $this->currentUserLabel(),
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
        $query = DB::connection('DATA_MYSQL')
            ->table('v_saldo_va as v');

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
        $map = $this->fetchSaldoMap([$custid]);

        return (int) ($map[$custid] ?? 0);
    }

    /**
     * Batch saldo untuk daftar CUSTID (max ~15) — 1 query, bukan N+1.
     *
     * @param  list<int>  $custids
     * @return array<int, int>
     */
    private function fetchSaldoMap(array $custids): array
    {
        $custids = array_values(array_unique(array_filter(array_map('intval', $custids))));
        if ($custids === []) {
            return [];
        }

        try {
            $rows = DB::connection('DATA_MYSQL')
                ->table('v_saldo_va')
                ->whereIn('CUSTID', $custids)
                ->get(['CUSTID', 'SALDO']);

            $map = [];
            foreach ($rows as $row) {
                $map[(int) $row->CUSTID] = (int) ($row->SALDO ?? 0);
            }

            return $map;
        } catch (\Throwable) {
            // fallback aggregate
        }

        $rows = DB::connection('DATA_MYSQL')
            ->table('sccttran')
            ->whereIn('CUSTID', $custids)
            ->groupBy('CUSTID')
            ->selectRaw('CUSTID, CAST(COALESCE(SUM(KREDIT),0) AS SIGNED) - CAST(COALESCE(SUM(DEBET),0) AS SIGNED) as saldo')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->CUSTID] = (int) ($row->saldo ?? 0);
        }

        return $map;
    }

    private function fetchTopupRows(int $custid)
    {
        if (!Schema::connection('DATA_MYSQL')->hasTable('sm_topup')) {
            return collect();
        }

        $userCol = $this->hasColumn('sm_topup', 'user') ? 'user' : (
            $this->hasColumn('sm_topup', 'User') ? 'User' : null
        );

        $select = [
            'TOPUPNO as no_transaksi',
            'TRXDATE as tgl_transaksi',
            'NOMINAL as topup',
        ];
        if ($userCol) {
            $select[] = DB::raw("`$userCol` as user");
        }

        return DB::connection('DATA_MYSQL')
            ->table('sm_topup')
            ->where('CUSTID', $custid)
            ->orderByDesc('TRXDATE')
            ->limit(50)
            ->get($select)
            ->map(function ($r) {
                $r->user = $r->user ?? '-';

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

    private function currentUserLabel(): string
    {
        $user = Auth::user();
        if (!$user) {
            return 'ADMIN';
        }
        foreach (['username', 'name', 'email'] as $f) {
            $v = trim((string) ($user->{$f} ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return 'ADMIN';
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
