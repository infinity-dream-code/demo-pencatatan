<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
use App\Support\SmartcardExcelExport;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransaksiBelanjaController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100, 200];

    private string $title = 'smartCARD';
    private string $mainTitle = 'Transaksi Belanja';

    public function index(Request $request): View
    {
        $isSearch = $request->boolean('search');
        $filters = $this->filtersFromRequest($request);
        $perPage = $this->resolvePerPage($request);

        $rows = $isSearch
            ? $this->fetchRows($filters, $perPage)
            : new LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);

        return view('admin.smartcard.transaksi_belanja.index', [
            'title' => $this->title,
            'mainTitle' => $this->mainTitle,
            'dataTitle' => $this->mainTitle,
            'filters' => $filters,
            'isSearch' => $isSearch,
            'rows' => $rows,
            'totalDebet' => $isSearch ? $this->sumDebet($filters) : 0,
            'thnAka' => $this->fetchThnAka(),
            'kelasOptions' => $this->fetchKelasOptions(),
            'kantinOptions' => $this->fetchKantinOptions(),
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filtersFromRequest($request);

        $rows = $this->baseQuery($filters)
            ->select([
                'scctcust.NMCUST as nama',
                'scctcust.NOCUST as nis',
                'scctcashout.TanggalKeluar as tgl_transaksi',
                'scctcashout.BILLAM as debet',
                DB::raw('COALESCE(NULLIF(TRIM(sm_kantin.NamaKantin), \'\'), TRIM(scctcashout.Teller)) as kantin'),
                'scctcust.DESC02 as kelas',
                'scctcust.DESC03 as kelompok',
            ])
            ->orderByDesc('scctcashout.TanggalKeluar')
            ->orderByDesc('scctcashout.urut')
            ->get();

        $exportRows = [];
        $no = 1;
        $total = 0;
        foreach ($rows as $row) {
            $debet = (float) ($row->debet ?? 0);
            $total += $debet;
            $tgl = '';
            if (!empty($row->tgl_transaksi)) {
                $tgl = SmartcardExcelExport::datetimeCell($row->tgl_transaksi, 'd-m-Y H:i:s');
            }

            $exportRows[] = [
                $no++,
                $row->nis ?? '',
                $row->nama ?? '',
                $tgl,
                $debet,
                $row->kantin ?? '',
                $row->kelas ?? '',
                $row->kelompok ?? '',
            ];
        }

        $exportRows[] = ['', '', '', 'TOTAL', $total, '', '', ''];

        return SmartcardExcelExport::download(
            'transaksi-belanja-' . date('Ymd-His'),
            ['No', 'NIS', 'Nama', 'Tgl Transaksi', 'Debet', 'Kantin', 'Kelas', 'Kelompok'],
            $exportRows
        );
    }

    private function filtersFromRequest(Request $request): array
    {
        return [
            'thn_akademik' => trim((string) $request->query('thn_akademik', '')),
            'thn_angkatan' => trim((string) $request->query('thn_angkatan', '')),
            'nis' => trim((string) $request->query('nis', '')),
            'nama' => trim((string) $request->query('nama', '')),
            'nama_kantin' => trim((string) $request->query('nama_kantin', '')),
            'dari_tanggal' => trim((string) $request->query('dari_tanggal', '')),
            'sampai_tanggal' => trim((string) $request->query('sampai_tanggal', '')),
            'kelas_id' => trim((string) $request->query('kelas_id', '')),
            'per_page' => (string) $this->resolvePerPage($request),
        ];
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 25);
        if (!in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            return 25;
        }

        return $perPage;
    }

    private function db()
    {
        return DB::connection('DATA_MYSQL');
    }

    private function fetchThnAka(): array
    {
        try {
            return $this->db()
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

    private function fetchKelasOptions(): array
    {
        try {
            return $this->db()
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

    private function fetchKantinOptions(): array
    {
        try {
            return $this->db()
                ->table('sm_kantin')
                ->whereNotNull('NamaKantin')
                ->whereRaw("TRIM(NamaKantin) <> ''")
                ->orderBy('NamaKantin')
                ->distinct()
                ->get(['NamaKantin'])
                ->map(static fn ($r) => trim((string) ($r->NamaKantin ?? '')))
                ->filter(static fn ($v) => $v !== '')
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function baseQuery(array $filters)
    {
        $query = $this->db()
            ->table('scctcashout')
            ->join('scctcust', 'scctcashout.CUSTID', '=', 'scctcust.CUSTID')
            ->leftJoin('sm_kantin', function ($join) {
                $join->on(DB::raw('TRIM(sm_kantin.username)'), '=', DB::raw('TRIM(scctcashout.Teller)'));
            })
            ->whereRaw('UPPER(TRIM(scctcashout.FIDBANK)) = ?', ['BUY']);

        $this->applySchoolScope($query);
        $this->applyFilters($query, $filters);

        return $query;
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

        if (($filters['nama_kantin'] ?? '') !== '') {
            $kantin = $filters['nama_kantin'];
            $query->where(function ($q) use ($kantin) {
                $q->where('sm_kantin.NamaKantin', 'like', '%' . $kantin . '%')
                    ->orWhere('scctcashout.Teller', 'like', '%' . $kantin . '%');
            });
        }

        if ($filters['thn_akademik'] !== '') {
            $this->applyAngkatanLike($query, $filters['thn_akademik']);
        }

        if ($filters['thn_angkatan'] !== '') {
            $this->applyAngkatanLike($query, $filters['thn_angkatan']);
        }

        if ($filters['dari_tanggal'] !== '') {
            $from = $this->parseDate($filters['dari_tanggal']);
            if ($from) {
                $query->where('scctcashout.TanggalKeluar', '>=', $from->startOfDay());
            }
        }

        if ($filters['sampai_tanggal'] !== '') {
            $to = $this->parseDate($filters['sampai_tanggal']);
            if ($to) {
                $query->where('scctcashout.TanggalKeluar', '<=', $to->endOfDay());
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
        $kelas = $this->db()
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

    private function fetchRows(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->select([
                'scctcust.NMCUST as nama',
                'scctcust.NOCUST as nis',
                'scctcashout.TanggalKeluar as tgl_transaksi',
                'scctcashout.BILLAM as debet',
                DB::raw('COALESCE(NULLIF(TRIM(sm_kantin.NamaKantin), \'\'), TRIM(scctcashout.Teller)) as kantin'),
                'scctcust.DESC02 as kelas',
                'scctcust.DESC03 as kelompok',
            ])
            ->orderByDesc('scctcashout.TanggalKeluar')
            ->orderByDesc('scctcashout.urut')
            ->paginate($perPage)
            ->withQueryString();
    }

    private function sumDebet(array $filters): float
    {
        return (float) ($this->baseQuery($filters)->sum('scctcashout.BILLAM') ?? 0);
    }
}
