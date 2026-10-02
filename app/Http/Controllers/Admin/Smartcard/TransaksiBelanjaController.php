<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TransaksiBelanjaController extends Controller
{
    private const PER_PAGE = 25;

    private string $title = 'smartCARD';
    private string $mainTitle = 'Transaksi Belanja';

    public function index(Request $request): View
    {
        $isSearch = $request->boolean('search');
        $filters = [
            'thn_akademik' => trim((string) $request->query('thn_akademik', '')),
            'thn_angkatan' => trim((string) $request->query('thn_angkatan', '')),
            'nis' => trim((string) $request->query('nis', '')),
            'nama' => trim((string) $request->query('nama', '')),
            'dari_tanggal' => trim((string) $request->query('dari_tanggal', '')),
            'sampai_tanggal' => trim((string) $request->query('sampai_tanggal', '')),
            'kelas_id' => trim((string) $request->query('kelas_id', '')),
        ];

        $rows = $isSearch
            ? $this->fetchRows($filters)
            : new LengthAwarePaginator([], 0, self::PER_PAGE, 1, [
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
        ]);
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

    private function fetchRows(array $filters): LengthAwarePaginator
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
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    private function sumDebet(array $filters): float
    {
        return (float) ($this->baseQuery($filters)->sum('scctcashout.BILLAM') ?? 0);
    }
}
