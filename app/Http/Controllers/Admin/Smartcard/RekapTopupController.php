<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
use App\Support\SmartcardExcelExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rekap TOPUP — sumber utama sm_topup.
 * Kolom User = sm_topup.users (login cyber_key.users).
 */
class RekapTopupController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100, 200];

    private ?string $smTopupUserCol = null;

    public function index(Request $request): View
    {
        $isSearch = $request->boolean('search');
        $filters = $this->filtersFromRequest($request);
        $perPage = $this->resolvePerPage($request);
        $this->smTopupUserCol = $this->detectSmTopupUserColumn();

        $thnAka = $this->fetchThnAka();
        $kelasOptions = $this->fetchKelasOptions();

        $rows = new LengthAwarePaginator([], 0, $perPage, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
        $totals = ['topup' => 0, 'fee' => 0, 'grand' => 0];
        $errorMessage = null;

        if ($isSearch) {
            try {
                $rows = $this->fetchRows($filters, $perPage);
                $totals = $this->sumTotalsSql($filters);
            } catch (\Throwable $e) {
                Log::error('Smartcard RekapTopup fetchRows failed', ['message' => $e->getMessage()]);
                report($e);
                $errorMessage = 'Gagal memuat data [sm_topup]: ' . $e->getMessage();
            }
        }

        return view('admin.smartcard.rekap_topup.index', [
            'title' => 'smartCARD',
            'mainTitle' => 'Rekap TOPUP',
            'dataTitle' => 'Rekap TOPUP',
            'filters' => $filters,
            'isSearch' => $isSearch,
            'rows' => $rows,
            'totals' => $totals,
            'thnAka' => $thnAka,
            'kelasOptions' => $kelasOptions,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'errorMessage' => $errorMessage,
        ]);
    }

    public function export(Request $request): StreamedResponse|RedirectResponse
    {
        $filters = $this->filtersFromRequest($request);
        $this->smTopupUserCol = $this->detectSmTopupUserColumn();

        try {
            $rows = $this->fetchAllRows($filters);
            $totals = $this->sumTotalsSql($filters);
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.smartcard.rekap-topup.index', array_merge($filters, ['search' => 1]))
                ->with('error', 'Gagal export: ' . $e->getMessage());
        }

        $exportRows = [];
        $no = 1;
        foreach ($rows as $row) {
            $exportRows[] = [
                $no++,
                $row->kelas ?? '',
                $row->gender ?? '',
                $row->lokasi ?? '',
                $row->nis ?? '',
                $row->nama ?? '',
                (int) ($row->topup ?? 0),
                SmartcardExcelExport::datetimeCell($row->tgl_transaksi ?? null),
                $row->no_transaksi ?? '',
                $row->user ?? '',
            ];
        }
        $exportRows[] = ['', '', '', '', '', 'TOTAL', (int) ($totals['topup'] ?? 0), '', '', ''];

        return SmartcardExcelExport::download(
            'rekap-topup-' . date('Ymd-His'),
            ['No', 'Kelas', 'Gender', 'Lokasi', 'NIS', 'Nama', 'TOPUP', 'Tgl Transaksi', 'No Transaksi', 'User'],
            $exportRows
        );
    }

    public function printRekap(Request $request): Response|RedirectResponse
    {
        $filters = $this->filtersFromRequest($request);
        $this->smTopupUserCol = $this->detectSmTopupUserColumn();

        try {
            $totalCount = (int) $this->baseQuery($filters)->count('tp.CUSTID');
            if ($totalCount <= 0) {
                return redirect()
                    ->route('admin.smartcard.rekap-topup.index', array_merge($filters, ['search' => 1]))
                    ->with('error', 'Tidak ada data rekap untuk dicetak.');
            }

            $totals = $this->sumTotalsSql($filters);
            $sekolahNama = $this->fetchSekolahNama();

            $pdf = Pdf::loadView('admin.smartcard.rekap_topup.rekap-pdf', [
                'sekolahNama' => $sekolahNama,
                'filters' => $filters,
                'rows' => $this->fetchAllRows($filters),
                'totals' => $totals,
            ])->setPaper('a4', 'landscape');

            return $pdf->stream('rekap-topup-uang-saku-' . date('Ymd-His') . '.pdf');
        } catch (\Throwable $e) {
            Log::error('Smartcard RekapTopup printRekap failed', ['message' => $e->getMessage()]);
            report($e);

            return redirect()
                ->route('admin.smartcard.rekap-topup.index', array_merge($filters, ['search' => 1]))
                ->with('error', 'Gagal mencetak: ' . $e->getMessage());
        }
    }

    private function filtersFromRequest(Request $request): array
    {
        return [
            'thn_angkatan' => trim((string) $request->input('thn_angkatan', $request->query('thn_angkatan', ''))),
            'kelas_id' => trim((string) $request->input('kelas_id', $request->query('kelas_id', ''))),
            'nis' => trim((string) $request->input('nis', $request->query('nis', ''))),
            'nama' => trim((string) $request->input('nama', $request->query('nama', ''))),
            'dari_tanggal' => trim((string) $request->input('dari_tanggal', $request->query('dari_tanggal', ''))),
            'sampai_tanggal' => trim((string) $request->input('sampai_tanggal', $request->query('sampai_tanggal', ''))),
            'per_page' => (string) $this->resolvePerPage($request),
        ];
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', $request->query('per_page', 25));
        if (!in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            return 25;
        }

        return $perPage;
    }

    private function detectColumn(string $table, string $column): bool
    {
        try {
            return Schema::connection('DATA_MYSQL')->hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }

    private function detectSmTopupUserColumn(): ?string
    {
        try {
            if (!Schema::connection('DATA_MYSQL')->hasTable('sm_topup')) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        foreach (['users', 'user', 'User'] as $col) {
            if ($this->detectColumn('sm_topup', $col)) {
                return $col;
            }
        }

        return null;
    }

    private function baseQuery(array $filters)
    {
        if (!Schema::connection('DATA_MYSQL')->hasTable('sm_topup')) {
            throw new \RuntimeException('Tabel sm_topup tidak tersedia.');
        }

        $sekolah = DB::connection('DATA_MYSQL')
            ->table('mst_sekolah')
            ->selectRaw('TRIM(CODE01) as code01, MAX(NULLIF(TRIM(DESC01), \'\')) as nama_sekolah')
            ->groupByRaw('TRIM(CODE01)');

        $query = DB::connection('DATA_MYSQL')
            ->table('sm_topup as tp')
            ->join('scctcust', 'tp.CUSTID', '=', 'scctcust.CUSTID')
            ->leftJoinSub($sekolah, 'sk', function ($join) {
                $join->on(DB::raw('sk.code01'), '=', DB::raw('TRIM(scctcust.CODE01)'));
            })
            ->where('tp.NOMINAL', '>', 0);

        $this->applySchoolScope($query);
        $this->applyFilters($query, $filters);

        return $query;
    }

    private function selectColumns(): array
    {
        $userExpr = $this->smTopupUserCol !== null
            ? "COALESCE(NULLIF(TRIM(tp.`{$this->smTopupUserCol}`), ''), '-')"
            : "'-'";

        return [
            'tp.CUSTID as custid',
            'scctcust.NOCUST as nis',
            'scctcust.NMCUST as nama',
            'tp.NOMINAL as topup',
            'tp.TRXDATE as tgl_transaksi',
            DB::raw("COALESCE(NULLIF(TRIM(tp.TOPUPNO), ''), '-') as no_transaksi"),
            DB::raw("{$userExpr} as user_raw"),
            DB::raw("COALESCE(NULLIF(TRIM(scctcust.DESC03), ''), NULLIF(TRIM(scctcust.DESC02), ''), '-') as kelas"),
            DB::raw("COALESCE(NULLIF(TRIM(scctcust.CODE04), ''), '-') as gender"),
            DB::raw("COALESCE(NULLIF(TRIM(sk.nama_sekolah), ''), NULLIF(TRIM(scctcust.DESC01), ''), '-') as lokasi"),
        ];
    }

    private function fetchRows(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->select($this->selectColumns())
            ->orderByDesc('tp.TRXDATE')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($row) => $this->mapRow($row));
    }

    private function fetchAllRows(array $filters): Collection
    {
        return $this->baseQuery($filters)
            ->select($this->selectColumns())
            ->orderByDesc('tp.TRXDATE')
            ->get()
            ->map(fn ($row) => $this->mapRow($row));
    }

    private function mapRow(object $row): object
    {
        $topup = (int) ($row->topup ?? 0);
        $row->topup = $topup;
        $row->fee = 0;
        $row->total = $topup;

        $rawUser = trim((string) ($row->user_raw ?? ''));
        $row->user = ($rawUser !== '' && $rawUser !== '-') ? $rawUser : '-';

        return $row;
    }

    /** @return array{topup: int, fee: int, grand: int} */
    private function sumTotalsSql(array $filters): array
    {
        $row = $this->baseQuery($filters)
            ->selectRaw('CAST(COALESCE(SUM(tp.NOMINAL), 0) AS SIGNED) as topup_sum')
            ->first();

        $topup = (int) ($row->topup_sum ?? 0);

        return [
            'topup' => $topup,
            'fee' => 0,
            'grand' => $topup,
        ];
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

    private function applyFilters($query, array $filters): void
    {
        if ($filters['nis'] !== '') {
            $query->where('scctcust.NOCUST', 'like', '%' . $filters['nis'] . '%');
        }

        if ($filters['nama'] !== '') {
            $query->where('scctcust.NMCUST', 'like', '%' . $filters['nama'] . '%');
        }

        if ($filters['thn_angkatan'] !== '') {
            $this->applyAngkatanLike($query, $filters['thn_angkatan']);
        }

        if ($filters['dari_tanggal'] !== '') {
            $from = $this->parseDate($filters['dari_tanggal']);
            if ($from) {
                $query->where('tp.TRXDATE', '>=', $from->startOfDay());
            }
        }

        if ($filters['sampai_tanggal'] !== '') {
            $to = $this->parseDate($filters['sampai_tanggal']);
            if ($to) {
                $query->where('tp.TRXDATE', '<=', $to->endOfDay());
            }
        }

        if ($filters['kelas_id'] !== '') {
            $this->applyKelasFilter($query, $filters['kelas_id']);
        }
    }

    private function applyAngkatanLike($query, string $value): void
    {
        $full = trim($value);
        $base = trim((string) preg_replace('#\s*-\s*.*$#', '', $full));
        $query->where(function ($q) use ($full, $base) {
            $q->whereRaw('TRIM(scctcust.DESC04) = ?', [$full]);
            if ($base !== '' && $base !== $full) {
                $q->orWhereRaw('TRIM(scctcust.DESC04) = ?', [$base])
                    ->orWhereRaw("REPLACE(TRIM(scctcust.DESC04), ' ', '') LIKE ?", [str_replace(' ', '', $base) . '%']);
            }
        });
    }

    private function applyKelasFilter($query, string $kelasId): void
    {
        $kelas = DB::connection('DATA_MYSQL')
            ->table('mst_kelas')
            ->where('id', (int) $kelasId)
            ->first(['id', 'unit', 'jenjang', 'kelas']);

        if (!$kelas) {
            $query->whereRaw('1 = 0');

            return;
        }

        $unit = trim((string) ($kelas->unit ?? ''));
        $jenjang = trim((string) ($kelas->jenjang ?? ''));
        $kelasNama = trim((string) ($kelas->kelas ?? ''));

        $query->where(function ($q) use ($kelasId, $unit, $jenjang, $kelasNama) {
            $q->whereRaw('TRIM(scctcust.CODE03) = ?', [(string) $kelasId]);
            if ($unit !== '' && $jenjang !== '' && $kelasNama !== '') {
                $q->orWhere(function ($q2) use ($unit, $jenjang, $kelasNama) {
                    $q2->whereRaw('TRIM(scctcust.CODE02) = ?', [$unit])
                        ->whereRaw('TRIM(scctcust.DESC02) = ?', [$jenjang])
                        ->whereRaw('TRIM(scctcust.DESC03) = ?', [$kelasNama]);
                });
            }
        });
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00') {
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

    /** @return list<object{thn_aka: string}> */
    private function fetchThnAka(): array
    {
        try {
            return DB::connection('DATA_MYSQL')
                ->table('mst_thn_aka')
                ->whereNotNull('thn_aka')
                ->where('thn_aka', '!=', '')
                ->orderByDesc('thn_aka')
                ->get(['thn_aka'])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<object> */
    private function fetchKelasOptions(): array
    {
        try {
            return DB::connection('DATA_MYSQL')
                ->table('mst_kelas')
                ->orderBy('unit')
                ->orderBy('jenjang')
                ->orderBy('kelas')
                ->get(['id', 'unit', 'jenjang', 'kelas'])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function fetchSekolahNama(): string
    {
        $unit = trim((string) (Auth::user()->unit ?? ''));
        if ($unit !== '') {
            try {
                $nama = DB::connection('DATA_MYSQL')
                    ->table('mst_sekolah')
                    ->where(function ($q) use ($unit) {
                        $q->whereRaw('TRIM(CODE01) = ?', [$unit])
                            ->orWhereRaw('TRIM(DESC01) = ?', [$unit]);
                    })
                    ->value('DESC01');

                if ($nama !== null && trim((string) $nama) !== '') {
                    return trim((string) $nama);
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        $appName = trim((string) config('app.name', ''));
        if ($appName !== '' && !preg_match('/laravel/i', $appName)) {
            return $appName;
        }

        return "Mu'allimaat Muhammadiyah Yogyakarta";
    }
}
