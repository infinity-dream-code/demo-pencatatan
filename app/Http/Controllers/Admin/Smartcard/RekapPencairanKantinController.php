<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RekapPencairanKantinController extends Controller
{
    private const MAX_ROWS = 500;

    public function index(Request $request): View
    {
        $kdMercan = trim((string) $request->query('kd_mercan', ''));
        $dariTanggal = trim((string) $request->query('dari_tanggal', ''));
        $sampaiTanggal = trim((string) $request->query('sampai_tanggal', ''));
        $namaPenerima = trim((string) $request->query('nama_penerima', ''));
        $nominal = trim((string) $request->query('nominal', ''));

        $cariTransaksi = $request->boolean('cari_transaksi');
        $liatPencairan = $request->boolean('liat_pencairan');

        $mercanOptions = $this->fetchMercanOptions();

        $transaksiRows = collect();
        $transaksiTotal = 0;
        if ($cariTransaksi) {
            [$transaksiRows, $transaksiTotal] = $this->fetchTransaksiRows($kdMercan, $dariTanggal, $sampaiTanggal);
            if ($nominal === '' && $transaksiTotal > 0) {
                $nominal = (string) (int) round($transaksiTotal);
            }
        }

        $pencairanRows = collect();
        $pencairanTotal = 0;
        if ($liatPencairan) {
            [$pencairanRows, $pencairanTotal] = $this->fetchPencairanRows(
                $kdMercan,
                $dariTanggal,
                $sampaiTanggal
            );
        }

        return view('admin.smartcard.pencairan_kantin.index', [
            'title' => 'smartCARD',
            'mainTitle' => 'Rekap Pencairan Kantin',
            'dataTitle' => 'Rekap Pencairan Kantin',
            'mercanOptions' => $mercanOptions,
            'kdMercan' => $kdMercan,
            'dariTanggal' => $dariTanggal,
            'sampaiTanggal' => $sampaiTanggal,
            'namaPenerima' => $namaPenerima,
            'nominal' => $nominal,
            'cariTransaksi' => $cariTransaksi,
            'liatPencairan' => $liatPencairan,
            'transaksiRows' => $transaksiRows,
            'transaksiTotal' => $transaksiTotal,
            'pencairanRows' => $pencairanRows,
            'pencairanTotal' => $pencairanTotal,
            'previewNoTerima' => $this->generateNoTerima(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kd_mercan' => ['required', 'string', 'max:50'],
            'dari_tanggal' => ['required', 'date'],
            'sampai_tanggal' => ['required', 'date', 'after_or_equal:dari_tanggal'],
            'nama_penerima' => ['required', 'string', 'max:100'],
            'nominal' => ['required', 'integer', 'min:1'],
        ], [
            'kd_mercan.required' => 'Pilih merchant terlebih dahulu.',
            'nama_penerima.required' => 'Nama penerima wajib diisi.',
            'nominal.required' => 'Nominal wajib diisi.',
            'nominal.min' => 'Nominal harus lebih dari 0.',
        ]);

        $kdMercan = trim($validated['kd_mercan']);
        $dari = Carbon::parse($validated['dari_tanggal'])->startOfDay();
        $sampai = Carbon::parse($validated['sampai_tanggal'])->endOfDay();

        $noTerima = $this->generateNoTerima();

        $payload = [
            'KDMERCAN' => $kdMercan,
            'NamaPenerima' => trim($validated['nama_penerima']),
            'TglTerima' => now()->format('Y-m-d H:i:s'),
            'Nominal' => (int) $validated['nominal'],
            'NoTerima' => $noTerima,
            'dari_tgl_tran' => $dari->format('Y-m-d'),
            'akhir_tgl_tran' => $sampai->format('Y-m-d'),
        ];

        // Al-Multazam: KDMERCAN sering NULL — simpan juga username agar Liat Pencairan cocok
        try {
            if (Schema::connection('DATA_MYSQL')->hasColumn('sm_mercan_cair', 'username')) {
                $payload['username'] = $kdMercan;
            }
        } catch (\Throwable) {
            // ignore
        }

        DB::connection('DATA_MYSQL')->table('sm_mercan_cair')->insert($payload);

        return redirect()
            ->route('admin.smartcard.pencairan-kantin.index', [
                'kd_mercan' => $kdMercan,
                'dari_tanggal' => $dari->format('Y-m-d'),
                'sampai_tanggal' => $sampai->format('Y-m-d'),
                'nama_penerima' => trim($validated['nama_penerima']),
                'nominal' => (int) $validated['nominal'],
                'liat_pencairan' => 1,
            ])
            ->with('success', 'Data pencairan berhasil disimpan. No Terima: ' . $noTerima);
    }

    /** Format: YYYYMMDD + urut 3 digit, contoh 20260607001 */
    private function generateNoTerima(): string
    {
        $prefix = now()->format('Ymd');

        $last = DB::connection('DATA_MYSQL')
            ->table('sm_mercan_cair')
            ->whereRaw('TRIM(NoTerima) LIKE ?', [$prefix . '%'])
            ->orderByDesc('NoTerima')
            ->value('NoTerima');

        $seq = 1;
        if ($last !== null && $last !== '') {
            $last = trim((string) $last);
            if (str_starts_with($last, $prefix) && strlen($last) > strlen($prefix)) {
                $tail = substr($last, strlen($prefix));
                if (ctype_digit($tail)) {
                    $seq = (int) $tail + 1;
                }
            }
        }

        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @return list<object{kode: string, nama: string}>
     */
    private function fetchMercanOptions(): array
    {
        $db = DB::connection('DATA_MYSQL');
        $options = [];
        $seen = [];

        $push = static function (string $kode, string $nama) use (&$options, &$seen): void {
            $kode = trim($kode);
            if ($kode === '' || isset($seen[$kode])) {
                return;
            }
            $seen[$kode] = true;
            $nama = trim($nama);
            $options[] = (object) [
                'kode' => $kode,
                'nama' => $nama !== '' ? $nama : $kode,
            ];
        };

        // 0) Username dari data pencairan (sumber utama Al-Multazam)
        try {
            if (Schema::connection('DATA_MYSQL')->hasTable('sm_mercan_cair')) {
                $rows = $db->table('sm_mercan_cair')
                    ->whereNotNull('username')
                    ->where('username', '!=', '')
                    ->distinct()
                    ->orderBy('username')
                    ->pluck('username');
                foreach ($rows as $u) {
                    $push((string) $u, (string) $u);
                }
            }
        } catch (\Throwable) {
            // continue
        }

        // 1) Master merchant
        try {
            if (Schema::connection('DATA_MYSQL')->hasTable('sm_mercan')) {
                $rows = $db->table('sm_mercan')
                    ->whereNotNull('KDMERCAN')
                    ->where('KDMERCAN', '!=', '')
                    ->orderBy('NamaMercan')
                    ->get();
                foreach ($rows as $row) {
                    $push(
                        (string) ($row->KDMERCAN ?? ''),
                        (string) ($row->NamaMercan ?? $row->KDMERCAN ?? '')
                    );
                }
            }
        } catch (\Throwable) {
            // continue
        }

        // 2) Master kantin (KDMERCAN atau username)
        try {
            if (Schema::connection('DATA_MYSQL')->hasTable('sm_kantin')) {
                $rows = $db->table('sm_kantin')->orderBy('NamaKantin')->get();
                foreach ($rows as $row) {
                    $kode = trim((string) ($row->KDMERCAN ?? ''));
                    if ($kode === '') {
                        $kode = trim((string) ($row->username ?? $row->Username ?? ''));
                    }
                    $nama = trim((string) ($row->NamaKantin ?? $row->NamaMercan ?? $kode));
                    $push($kode, $nama);
                }
            }
        } catch (\Throwable) {
            // continue
        }

        // 3) Fallback: Teller unik dari scctcashout (BUY) — live only
        if ($options === []) {
            try {
                $tellers = $db->table('scctcashout')
                    ->whereRaw('UPPER(TRIM(FIDBANK)) = ?', ['BUY'])
                    ->whereNotNull('Teller')
                    ->where('Teller', '!=', '')
                    ->distinct()
                    ->orderBy('Teller')
                    ->limit(500)
                    ->pluck('Teller');
                foreach ($tellers as $teller) {
                    $push((string) $teller, (string) $teller);
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        usort($options, static fn ($a, $b) => strcasecmp($a->nama, $b->nama));

        return $options;
    }

    /**
     * @return array{0: Collection<int, object>, 1: float}
     */
    private function fetchTransaksiRows(string $kdMercan, string $dari, string $sampai): array
    {
        $query = DB::connection('DATA_MYSQL')
            ->table('scctcashout')
            ->join('scctcust', 'scctcashout.CUSTID', '=', 'scctcust.CUSTID')
            ->leftJoin('sm_kantin', function ($join) {
                $join->on(DB::raw('TRIM(sm_kantin.username)'), '=', DB::raw('TRIM(scctcashout.Teller)'));
            })
            ->leftJoin('sm_mercan', function ($join) {
                $join->on(DB::raw('TRIM(sm_mercan.KDMERCAN)'), '=', DB::raw('TRIM(sm_kantin.KDMERCAN)'));
            })
            ->whereRaw('UPPER(TRIM(scctcashout.FIDBANK)) = ?', ['BUY']);

        $this->applySchoolScope($query);

        if ($kdMercan !== '') {
            // Bisa kode merchant ATAU username teller kantin
            $query->where(function ($q) use ($kdMercan) {
                $q->whereRaw('TRIM(COALESCE(sm_kantin.KDMERCAN, \'\')) = ?', [$kdMercan])
                    ->orWhereRaw('TRIM(COALESCE(sm_kantin.username, \'\')) = ?', [$kdMercan])
                    ->orWhereRaw('TRIM(COALESCE(scctcashout.Teller, \'\')) = ?', [$kdMercan]);
            });
        }

        $from = $this->parseDate($dari);
        $to = $this->parseDate($sampai);
        if ($from) {
            $query->where('scctcashout.TanggalKeluar', '>=', $from->copy()->startOfDay());
        }
        if ($to) {
            $query->where('scctcashout.TanggalKeluar', '<=', $to->copy()->endOfDay());
        }

        $rows = $query
            ->select([
                'scctcashout.TanggalKeluar as tgl_transaksi',
                'scctcashout.BILLAM as saldo',
                DB::raw('COALESCE(NULLIF(TRIM(sm_mercan.NamaMercan), \'\'), TRIM(sm_kantin.KDMERCAN), \'-\') as mercan'),
                DB::raw('COALESCE(NULLIF(TRIM(sm_kantin.NamaKantin), \'\'), TRIM(scctcashout.Teller), \'-\') as kantin'),
            ])
            ->orderByDesc('scctcashout.TanggalKeluar')
            ->orderByDesc('scctcashout.urut')
            ->limit(self::MAX_ROWS)
            ->get();

        $total = (float) $rows->sum(static fn ($r) => (float) ($r->saldo ?? 0));

        return [$rows, $total];
    }

    private function applySchoolScope($query): void
    {
        $unit = trim((string) (Auth::user()->unit ?? ''));
        if ($unit === '') {
            return;
        }

        $query->where(function ($q) use ($unit) {
            $q->whereRaw('TRIM(scctcust.CODE01) = ?', [$unit])
                ->orWhereRaw('TRIM(scctcust.CODE02) = ?', [$unit]);
        });
    }

    /**
     * @return array{0: Collection<int, object>, 1: float}
     */
    private function fetchPencairanRows(string $kdMercan, string $dari, string $sampai): array
    {
        $query = DB::connection('DATA_MYSQL')
            ->table('sm_mercan_cair')
            ->orderByDesc('TglTerima')
            ->orderByDesc('urut');

        // Di Al-Multazam KDMERCAN sering NULL — kunci merchant = username
        if ($kdMercan !== '') {
            $query->where(function ($q) use ($kdMercan) {
                $q->whereRaw('TRIM(COALESCE(KDMERCAN, \'\')) = ?', [$kdMercan])
                    ->orWhereRaw('TRIM(COALESCE(username, \'\')) = ?', [$kdMercan]);
            });
        }

        $from = $this->parseDate($dari);
        $to = $this->parseDate($sampai);
        // Prefer filter TglTerima (dari_tgl_tran / akhir_tgl_tran sering NULL)
        if ($from) {
            $query->where('TglTerima', '>=', $from->copy()->startOfDay());
        }
        if ($to) {
            $query->where('TglTerima', '<=', $to->copy()->endOfDay());
        }

        $rows = $query
            ->select([
                'TglTerima as tgl_terima',
                'NamaPenerima as nama_penerima',
                'Nominal as nominal',
                'NoTerima as no_terima',
                'dari_tgl_tran',
                'akhir_tgl_tran',
                'username',
                'KDMERCAN',
            ])
            ->limit(self::MAX_ROWS)
            ->get();

        $total = (float) $rows->sum(static fn ($r) => (float) ($r->nominal ?? 0));

        return [$rows, $total];
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
