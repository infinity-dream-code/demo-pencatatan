<?php

namespace App\Http\Controllers\Admin\Pencatatan;

use App\Http\Controllers\Admin\Pencatatan\Concerns\QueriesAktJurnal;
use App\Http\Controllers\Controller;
use App\Models\akun_kas_keluar;
use App\Models\akun_kas_masuk;
use App\Models\akt_jurnal;
use App\Support\PencatatanJurnalTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CekPencatatanController extends Controller
{
    use QueriesAktJurnal;

    public string $title = 'Pencatatan Sederhana';
    public string $mainTitle = 'Cek Pencatatan';
    public string $dataTitle = 'Cek Pencatatan Kas Masuk dan Kas Keluar';

    public function index()
    {
        $periodes = [];
        $akunMasuk = collect();
        $akunKeluar = collect();

        try {
            if (Schema::connection('DATA_MYSQL')->hasTable(PencatatanJurnalTable::name())) {
                $periodes = akt_jurnal::query()
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
            Log::warning('CekPencatatan index lookup failed', ['message' => $e->getMessage()]);
        }

        return view('admin.pencatatan.cek_pencatatan.index', [
            'title' => $this->title,
            'mainTitle' => $this->mainTitle,
            'dataTitle' => $this->dataTitle,
            'periodes' => $periodes,
            'defaultPeriode' => $periodes[0] ?? '',
            'akunMasuk' => $akunMasuk,
            'akunKeluar' => $akunKeluar,
            'columnsUrl' => route('admin.pencatatan-sederhana.cek-pencatatan.get-column'),
            'datasUrl' => route('admin.pencatatan-sederhana.cek-pencatatan.get-data'),
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
            ['data' => 'keterangan', 'name' => 'Keterangan', 'searchable' => true, 'orderable' => true],
            ['data' => 'foto', 'name' => 'Foto', 'searchable' => false, 'orderable' => false],
            ['data' => 'periode', 'name' => 'Periode', 'searchable' => true, 'orderable' => true],
            ['data' => 'tahun', 'name' => 'Tahun', 'searchable' => true, 'orderable' => true],
            ['data' => 'akun_kas_keluar', 'name' => 'Akun Kas Keluar', 'searchable' => true, 'orderable' => true],
            ['data' => 'akun_kas_masuk', 'name' => 'Akun Kas Masuk', 'searchable' => true, 'orderable' => true],
        ];
    }

    public function getData(Request $request)
    {
        try {
            $draw = (int) $request->get('draw', 0);
            $start = (int) $request->get('start', 0);
            $rowperpage = (int) $request->get('length', 10);

            $columnIndexArr = $request->get('order', []);
            $columnNameArr = $request->get('columns', []);
            $searchArr = $request->get('search', []);
            $searchValue = trim((string) ($searchArr['value'] ?? ''));

            $filter = $request->input('filter', []);
            if (!is_array($filter)) {
                $filter = [];
            }

            $sortMap = $this->jurnalSortMap();
            $columnName = 'tanggal';
            $columnSortOrder = 'asc';

            if (!empty($columnIndexArr)) {
                $columnIndex = (int) ($columnIndexArr[0]['column'] ?? 0);
                $columnSortOrder = strtolower((string) ($columnIndexArr[0]['dir'] ?? 'asc'));
                $columnSortOrder = in_array($columnSortOrder, ['asc', 'desc'], true) ? $columnSortOrder : 'asc';
                $requested = (string) ($columnNameArr[$columnIndex]['data'] ?? 'tanggal');
                $columnName = $sortMap[$requested] ?? 'tanggal';
            }

            $baseQuery = $this->jurnalBaseQuery();
            $totalRecords = (clone $baseQuery)->count();

            $filteredQuery = clone $baseQuery;
            $this->applyJurnalFilters($filteredQuery, $filter, $searchValue);
            $totalRecordswithFilter = (clone $filteredQuery)->count();

            $saldo = $this->calculateJurnalSaldo($filter, $searchValue);

            $records = $filteredQuery
                ->orderBy($columnName, $columnSortOrder)
                ->orderBy('urut', 'asc')
                ->skip($start)
                ->take($rowperpage)
                ->get()
                ->map(fn ($item) => $this->mapJurnalRow($item))
                ->toArray();

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalRecordswithFilter,
                'data' => $records,
                'saldo' => $saldo,
            ]);
        } catch (\Throwable $e) {
            Log::error('CekPencatatan getData failed', [
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
                'message' => 'Gagal memuat data cek pencatatan',
            ], 500);
        }
    }
}
