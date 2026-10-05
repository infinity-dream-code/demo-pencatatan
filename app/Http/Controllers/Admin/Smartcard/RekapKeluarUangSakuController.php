<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
use App\Support\SmartcardExcelExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rekap Keluar Uang Saku — dari scctcashout FIDBANK=CASH.
 * Kolom User = Teller / users (login cyber_key.users).
 */
class RekapKeluarUangSakuController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100, 200];

    private const TABLE = 'scctcashout';

    public function index(Request $request): View
    {
        $isSearch = $request->boolean('search');
        $filters = $this->filtersFromRequest($request);
        $perPage = $this->resolvePerPage($request);

        $thnAka = $this->fetchThnAka();
        $kelasOptions = $this->fetchKelasOptions();

        $rows = new LengthAwarePaginator([], 0, $perPage, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
        $totals = ['debet' => 0];
        $errorMessage = null;

        if ($isSearch) {
            try {
                $rows = $this->fetchRows($filters, $perPage);
                $totals = $this->sumTotalsSql($filters);
            } catch (\Throwable $e) {
                $errorMessage = 'Gagal memuat data: ' . $e->getMessage();
            }
        }

        return view('admin.smartcard.rekap_keluar_uang_saku.index', [
            'title' => 'smartCARD',
            'mainTitle' => 'Rekap Keluar Uang Saku',
            'dataTitle' => 'Rekap Keluar Uang Saku',
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

        try {
            $rows = $this->fetchAllRows($filters);
            $totals = $this->sumTotalsSql($filters);
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.smartcard.rekap-keluar-uang-saku.index', array_merge($filters, ['search' => 1]))
                ->with('error', 'Gagal export: ' . $e->getMessage());
        }

        $exportRows = [];
        $no = 1;
        foreach ($rows as $row) {
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
                $row->kelas ?? '',
                $row->gender ?? '',
                $row->lokasi ?? '',
                $row->nis ?? '',
                $row->nama ?? '',
                (int) ($row->debet ?? 0),
                $tgl,
                $row->no_transaksi ?? '',
                $row->user ?? '',
            ];
        }
        $exportRows[] = ['', '', '', '', '', 'TOTAL', (int) ($totals['debet'] ?? 0), '', '', ''];

        return SmartcardExcelExport::download(
            'rekap-keluar-uang-saku-' . date('Ymd-His'),
            ['No', 'Kelas', 'Gender', 'Lokasi', 'NIS', 'Nama', 'Keluar', 'Tgl Transaksi', 'No Transaksi', 'User'],
            $exportRows
        );
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

    private function hasColumn(string $column): bool
    {
        try {
            return Schema::connection('DATA_MYSQL')->hasColumn(self::TABLE, $column);
        } catch (\Throwable) {
            return false;
        }
    }

    private function baseQuery(array $filters)
    {
        $query = DB::connection('DATA_MYSQL')
            ->table(self::TABLE . ' as co')
            ->join('scctcust', 'co.CUSTID', '=', 'scctcust.CUSTID')
            ->leftJoin('mst_kelas', DB::raw('CAST(mst_kelas.id AS CHAR)'), '=', DB::raw('TRIM(scctcust.CODE03)'))
            ->leftJoin('mst_sekolah', DB::raw('TRIM(mst_sekolah.CODE01)'), '=', DB::raw('TRIM(scctcust.CODE01)'))
            ->whereRaw('UPPER(TRIM(co.FIDBANK)) = ?', ['CASH'])
            ->where('co.BILLAM', '>', 0);

        $this->applySchoolScope($query);
        $this->applyFilters($query, $filters);

        return $query;
    }

    private function selectColumns(): array
    {
        $userParts = [];
        if ($this->hasColumn('users')) {
            $userParts[] = "NULLIF(TRIM(co.users), '')";
        }
        if ($this->hasColumn('Teller')) {
            $userParts[] = "NULLIF(TRIM(co.Teller), '')";
        } elseif ($this->hasColumn('teller')) {
            $userParts[] = "NULLIF(TRIM(co.teller), '')";
        }
        $userExpr = $userParts === []
            ? "'-'"
            : 'COALESCE(' . implode(', ', $userParts) . ", '-')";

        return [
            'co.CUSTID as custid',
            'scctcust.NOCUST as nis',
            'scctcust.NMCUST as nama',
            'co.BILLAM as debet',
            'co.TanggalKeluar as tgl_transaksi',
            DB::raw("COALESCE(NULLIF(TRIM(co.TRANSNO), ''), '-') as no_transaksi"),
            DB::raw("{$userExpr} as user_login"),
            DB::raw("COALESCE(NULLIF(TRIM(mst_kelas.jenjang), ''), TRIM(scctcust.DESC02), '-') as kelas"),
            DB::raw("COALESCE(NULLIF(TRIM(mst_kelas.kelas), ''), TRIM(scctcust.DESC03), '-') as kelompok"),
            DB::raw("COALESCE(NULLIF(TRIM(scctcust.CODE04), ''), '-') as gender"),
            DB::raw("COALESCE(NULLIF(TRIM(mst_sekolah.DESC01), ''), '-') as lokasi"),
        ];
    }

    private function fetchRows(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->select($this->selectColumns())
            ->orderByDesc('co.TanggalKeluar')
            ->orderByDesc('co.urut')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($row) => $this->mapRow($row));
    }

    private function mapRow(object $row): object
    {
        $row->user = trim((string) ($row->user_login ?? '')) !== ''
            ? trim((string) $row->user_login)
            : '-';

        return $row;
    }

    /** @return array{debet: int} */
    private function sumTotalsSql(array $filters): array
    {
        $row = $this->baseQuery($filters)
            ->selectRaw('CAST(COALESCE(SUM(co.BILLAM), 0) AS SIGNED) as debet_sum')
            ->first();

        return ['debet' => (int) ($row->debet_sum ?? 0)];
    }

    private function fetchAllRows(array $filters): Collection
    {
        return $this->baseQuery($filters)
            ->select($this->selectColumns())
            ->orderByDesc('co.TanggalKeluar')
            ->orderByDesc('co.urut')
            ->get()
            ->map(fn ($row) => $this->mapRow($row));
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
                $query->where('co.TanggalKeluar', '>=', $from->startOfDay());
            }
        }

        if ($filters['sampai_tanggal'] !== '') {
            $to = $this->parseDate($filters['sampai_tanggal']);
            if ($to) {
                $query->where('co.TanggalKeluar', '<=', $to->endOfDay());
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
                    ->orWhereRaw('REPLACE(TRIM(scctcust.DESC04), \' \', \'\') LIKE ?', [str_replace(' ', '', $base) . '%']);
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
}
