<?php

namespace App\Http\Controllers\Admin\Pencatatan;

use App\Http\Controllers\Controller;
use App\Models\akun_kas_keluar;
use App\Models\akun_kas_masuk;
use App\Models\akt_jurnal_in_out;
use App\Support\SmartcardExcelExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RekapExportExcelController extends Controller
{
    public string $title = 'Pencatatan Sederhana';
    public string $mainTitle = 'Rekap Export Excel';
    public string $dataTitle = 'Rekap export excel Kas Masuk dan Kas Keluar';

    public function index()
    {
        $periodes = [];
        $akunMasuk = collect();
        $akunKeluar = collect();

        try {
            if (Schema::connection('DATA_MYSQL')->hasTable('akt_jurnal_in_out')) {
                $periodes = akt_jurnal_in_out::query()
                    ->whereNotNull('periode')
                    ->where('periode', '!=', '')
                    ->distinct()
                    ->orderByDesc('periode')
                    ->pluck('periode')
                    ->values()
                    ->all();
            }

            if (Schema::connection('DATA_MYSQL')->hasTable('akun_kas_masuk')) {
                $akunMasuk = akun_kas_masuk::query()
                    ->orderBy('KodeAkunMasuk')
                    ->get(['KodeAkunMasuk', 'NamaAkunMasuk']);
            }

            if (Schema::connection('DATA_MYSQL')->hasTable('akun_kas_keluar')) {
                $akunKeluar = akun_kas_keluar::query()
                    ->orderBy('KodeAkunKeluar')
                    ->get(['KodeAkunKeluar', 'NamaAkunKeluar']);
            }
        } catch (\Throwable $e) {
            Log::warning('RekapExportExcel index lookup failed', ['message' => $e->getMessage()]);
        }

        return view('admin.pencatatan.rekap_export_excel.index', [
            'title' => $this->title,
            'mainTitle' => $this->mainTitle,
            'dataTitle' => $this->dataTitle,
            'periodes' => $periodes,
            'defaultPeriode' => $periodes[0] ?? '',
            'akunMasuk' => $akunMasuk,
            'akunKeluar' => $akunKeluar,
            'columnsUrl' => route('admin.pencatatan-sederhana.rekap-export-excel.get-column'),
            'datasUrl' => route('admin.pencatatan-sederhana.rekap-export-excel.get-data'),
            'exportUrl' => route('admin.pencatatan-sederhana.rekap-export-excel.export'),
        ]);
    }

    public function getColumn()
    {
        return [
            ['data' => null, 'name' => 'no', 'className' => 'text-center', 'columnType' => 'row'],
            ['data' => 'tanggal', 'name' => 'Tanggal', 'searchable' => true, 'orderable' => true],
            ['data' => 'no_bukti', 'name' => 'No Bukti', 'searchable' => true, 'orderable' => true],
            [
                'data' => 'nominal_keluar',
                'name' => 'Nominal Kas Keluar',
                'searchable' => false,
                'orderable' => true,
                'columnType' => 'currency',
                'className' => 'text-end',
            ],
            [
                'data' => 'nominal_masuk',
                'name' => 'Nominal Kas Masuk',
                'searchable' => false,
                'orderable' => true,
                'columnType' => 'currency',
                'className' => 'text-end',
            ],
            ['data' => 'foto', 'name' => 'Foto', 'searchable' => false, 'orderable' => false],
            ['data' => 'periode', 'name' => 'Periode', 'searchable' => true, 'orderable' => true],
            ['data' => 'tahun', 'name' => 'Tahun', 'searchable' => true, 'orderable' => true],
            ['data' => 'keterangan', 'name' => 'Keterangan', 'searchable' => true, 'orderable' => true],
            [
                'data' => 'saldo',
                'name' => 'Saldo',
                'searchable' => false,
                'orderable' => false,
                'columnType' => 'currency',
                'className' => 'text-end',
            ],
            ['data' => 'akun_kas_keluar', 'name' => 'Akun Kas Keluar', 'searchable' => true, 'orderable' => true],
            ['data' => 'akun_kas_masuk', 'name' => 'Akun Kas Masuk', 'searchable' => true, 'orderable' => true],
        ];
    }

    public function getData(Request $request)
    {
        try {
            $draw = (int) $request->get('draw', 0);
            $start = (int) $request->get('start', 0);
            $rowperpage = (int) $request->get('length', 25);

            [$filters, $searchValue] = $this->extractFilters($request);
            [$columnName, $columnSortOrder] = $this->resolveSort($request);

            $baseQuery = $this->baseQuery();
            $totalRecords = (clone $baseQuery)->count();

            $filteredQuery = clone $baseQuery;
            $this->applyFilters($filteredQuery, $filters, $searchValue);
            $totalRecordswithFilter = (clone $filteredQuery)->count();
            $saldo = $this->calculateSaldo($filters, $searchValue);

            $records = $filteredQuery
                ->orderBy($columnName, $columnSortOrder)
                ->orderBy('urut', 'asc')
                ->skip($start)
                ->take($rowperpage)
                ->get()
                ->map(fn ($item) => $this->mapRow($item, $saldo))
                ->toArray();

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalRecordswithFilter,
                'data' => $records,
                'saldo' => $saldo,
            ]);
        } catch (\Throwable $e) {
            Log::error('RekapExportExcel getData failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'draw' => (int) $request->get('draw', 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'saldo' => 0,
                'message' => 'Gagal memuat data rekap export excel',
            ], 500);
        }
    }

    public function export(Request $request): StreamedResponse
    {
        [$filters, $searchValue] = $this->extractFilters($request);
        [$columnName, $columnSortOrder] = $this->resolveSort($request);

        $query = $this->baseQuery();
        $this->applyFilters($query, $filters, $searchValue);
        $saldo = $this->calculateSaldo($filters, $searchValue);

        $rows = $query
            ->orderBy($columnName, $columnSortOrder)
            ->orderBy('urut', 'asc')
            ->get()
            ->map(function ($item) use ($saldo) {
                $mapped = $this->mapRow($item, $saldo);
                return [
                    SmartcardExcelExport::datetimeCell($mapped['tanggal']),
                    $mapped['no_bukti'],
                    $mapped['nominal_keluar'],
                    $mapped['nominal_masuk'],
                    $mapped['foto'],
                    $mapped['periode'],
                    $mapped['tahun'],
                    $mapped['keterangan'],
                    $mapped['saldo'],
                    $mapped['akun_kas_keluar'],
                    $mapped['akun_kas_masuk'],
                ];
            })
            ->all();

        $rows[] = ['', '', '', '', '', '', '', 'SALDO', $saldo, '', ''];

        $periode = $filters['periode'] !== '' && strtolower($filters['periode']) !== 'all'
            ? $filters['periode']
            : 'semua';

        return SmartcardExcelExport::download(
            'rekap_kas_masuk_keluar_' . $periode,
            [
                'Tanggal',
                'No Bukti',
                'Nominal Kas Keluar',
                'Nominal Kas Masuk',
                'Foto',
                'Periode',
                'Tahun',
                'Keterangan',
                'Saldo',
                'Akun Kas Keluar',
                'Akun Kas Masuk',
            ],
            $rows
        );
    }

    private function baseQuery(): Builder
    {
        return akt_jurnal_in_out::query()->select([
            'urut',
            'tanggal',
            'no_bukti',
            'keterangan',
            'debet',
            'kredit',
            'tahun',
            'periode',
            'buktiurl',
            'NamaAkunMasuk',
            'NamaAkunKeluar',
        ]);
    }

    /**
     * @return array{0: array<string, string>, 1: string}
     */
    private function extractFilters(Request $request): array
    {
        $filter = $request->input('filter', []);
        if (!is_array($filter)) {
            $filter = [];
        }

        $searchArr = $request->get('search', []);
        $searchValue = trim((string) ($searchArr['value'] ?? ''));

        return [[
            'periode' => trim((string) ($filter['periode'] ?? '')),
            'no_bukti' => trim((string) ($filter['no_bukti'] ?? '')),
            'keterangan' => trim((string) ($filter['keterangan'] ?? '')),
            'tanggal_dari' => trim((string) ($filter['tanggal_dari'] ?? '')),
            'tanggal_sampai' => trim((string) ($filter['tanggal_sampai'] ?? '')),
            'akun_masuk' => trim((string) ($filter['akun_masuk'] ?? '')),
            'akun_keluar' => trim((string) ($filter['akun_keluar'] ?? '')),
        ], $searchValue];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveSort(Request $request): array
    {
        $sortMap = [
            'tanggal' => 'tanggal',
            'no_bukti' => 'no_bukti',
            'nominal_keluar' => 'debet',
            'nominal_masuk' => 'kredit',
            'keterangan' => 'keterangan',
            'periode' => 'periode',
            'tahun' => 'tahun',
            'akun_kas_keluar' => 'NamaAkunKeluar',
            'akun_kas_masuk' => 'NamaAkunMasuk',
        ];

        $columnName = 'tanggal';
        $columnSortOrder = 'asc';
        $columnIndexArr = $request->get('order', []);
        $columnNameArr = $request->get('columns', []);

        if (!empty($columnIndexArr)) {
            $columnIndex = (int) ($columnIndexArr[0]['column'] ?? 0);
            $columnSortOrder = strtolower((string) ($columnIndexArr[0]['dir'] ?? 'asc'));
            $columnSortOrder = in_array($columnSortOrder, ['asc', 'desc'], true) ? $columnSortOrder : 'asc';
            $requested = (string) ($columnNameArr[$columnIndex]['data'] ?? 'tanggal');
            $columnName = $sortMap[$requested] ?? 'tanggal';
        }

        return [$columnName, $columnSortOrder];
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function applyFilters(Builder $query, array $filters, string $searchValue): void
    {
        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                $q->where('no_bukti', 'like', '%' . $searchValue . '%')
                    ->orWhere('keterangan', 'like', '%' . $searchValue . '%')
                    ->orWhere('periode', 'like', '%' . $searchValue . '%')
                    ->orWhere('tahun', 'like', '%' . $searchValue . '%')
                    ->orWhere('NamaAkunMasuk', 'like', '%' . $searchValue . '%')
                    ->orWhere('NamaAkunKeluar', 'like', '%' . $searchValue . '%');
            });
        }

        if ($filters['periode'] !== '' && strtolower($filters['periode']) !== 'all') {
            $query->where('periode', $filters['periode']);
        }

        if ($filters['no_bukti'] !== '') {
            $query->where('no_bukti', 'like', '%' . $filters['no_bukti'] . '%');
        }

        if ($filters['keterangan'] !== '') {
            $query->where('keterangan', 'like', '%' . $filters['keterangan'] . '%');
        }

        if ($filters['tanggal_dari'] !== '' && $filters['tanggal_dari'] !== '0000-00-00') {
            $query->whereDate('tanggal', '>=', $filters['tanggal_dari']);
        }

        if ($filters['tanggal_sampai'] !== '' && $filters['tanggal_sampai'] !== '0000-00-00') {
            $query->whereDate('tanggal', '<=', $filters['tanggal_sampai']);
        }

        if ($filters['akun_masuk'] !== '' && strtolower($filters['akun_masuk']) !== 'all') {
            $query->where('NamaAkunMasuk', $filters['akun_masuk']);
        }

        if ($filters['akun_keluar'] !== '' && strtolower($filters['akun_keluar']) !== 'all') {
            $query->where('NamaAkunKeluar', $filters['akun_keluar']);
        }
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function calculateSaldo(array $filters, string $searchValue): int
    {
        $saldoQuery = akt_jurnal_in_out::query();
        $this->applyFilters($saldoQuery, $filters, $searchValue);
        $aggregate = $saldoQuery
            ->selectRaw('COALESCE(SUM(kredit), 0) as total_masuk, COALESCE(SUM(debet), 0) as total_keluar')
            ->first();

        return (int) ($aggregate->total_masuk ?? 0) - (int) ($aggregate->total_keluar ?? 0);
    }

    private function mapRow(object $item, int $saldo): array
    {
        $bukti = trim((string) ($item->buktiurl ?? ''));
        if ($bukti === '' || $bukti === '-') {
            $bukti = '-';
        }

        return [
            'item_id' => $item->urut,
            'urut' => $item->urut,
            'tanggal' => $item->tanggal,
            'no_bukti' => $item->no_bukti ?? '',
            'nominal_keluar' => (int) ($item->debet ?? 0),
            'nominal_masuk' => (int) ($item->kredit ?? 0),
            'foto' => $bukti,
            'periode' => $item->periode ?? '',
            'tahun' => $item->tahun ?? '',
            'keterangan' => $item->keterangan ?? '',
            'saldo' => $saldo,
            'akun_kas_keluar' => $item->NamaAkunKeluar ?: '-',
            'akun_kas_masuk' => $item->NamaAkunMasuk ?: '-',
        ];
    }
}
