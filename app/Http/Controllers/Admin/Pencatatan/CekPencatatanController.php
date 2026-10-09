<?php

namespace App\Http\Controllers\Admin\Pencatatan;

use App\Http\Controllers\Controller;
use App\Models\akun_kas_keluar;
use App\Models\akun_kas_masuk;
use App\Models\akt_jurnal_in_out;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CekPencatatanController extends Controller
{
    public string $title = 'Pencatatan Sederhana';
    public string $mainTitle = 'Cek Pencatatan';
    public string $dataTitle = 'Cek Pencatatan Kas Masuk dan Kas Keluar';

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
            Log::warning('CekPencatatan index lookup failed', ['message' => $e->getMessage()]);
        }

        $defaultPeriode = $periodes[0] ?? '';

        return view('admin.pencatatan.cek_pencatatan.index', [
            'title' => $this->title,
            'mainTitle' => $this->mainTitle,
            'dataTitle' => $this->dataTitle,
            'periodes' => $periodes,
            'defaultPeriode' => $defaultPeriode,
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

            $filterPeriode = trim((string) ($filter['periode'] ?? ''));
            $filterNoBukti = trim((string) ($filter['no_bukti'] ?? ''));
            $filterKeterangan = trim((string) ($filter['keterangan'] ?? ''));
            $filterTanggalDari = trim((string) ($filter['tanggal_dari'] ?? ''));
            $filterTanggalSampai = trim((string) ($filter['tanggal_sampai'] ?? ''));
            $filterAkunMasuk = trim((string) ($filter['akun_masuk'] ?? ''));
            $filterAkunKeluar = trim((string) ($filter['akun_keluar'] ?? ''));

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

            if (!empty($columnIndexArr)) {
                $columnIndex = (int) ($columnIndexArr[0]['column'] ?? 0);
                $columnSortOrder = strtolower((string) ($columnIndexArr[0]['dir'] ?? 'asc'));
                $columnSortOrder = in_array($columnSortOrder, ['asc', 'desc'], true) ? $columnSortOrder : 'asc';
                $requested = (string) ($columnNameArr[$columnIndex]['data'] ?? 'tanggal');
                $columnName = $sortMap[$requested] ?? 'tanggal';
            }

            $baseQuery = akt_jurnal_in_out::query()->select([
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

            $applyFilters = function ($query) use (
                $searchValue,
                $filterPeriode,
                $filterNoBukti,
                $filterKeterangan,
                $filterTanggalDari,
                $filterTanggalSampai,
                $filterAkunMasuk,
                $filterAkunKeluar
            ) {
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

                if ($filterPeriode !== '' && strtolower($filterPeriode) !== 'all') {
                    $query->where('periode', $filterPeriode);
                }

                if ($filterNoBukti !== '') {
                    $query->where('no_bukti', 'like', '%' . $filterNoBukti . '%');
                }

                if ($filterKeterangan !== '') {
                    $query->where('keterangan', 'like', '%' . $filterKeterangan . '%');
                }

                if ($filterTanggalDari !== '' && $filterTanggalDari !== '0000-00-00') {
                    $query->whereDate('tanggal', '>=', $filterTanggalDari);
                }

                if ($filterTanggalSampai !== '' && $filterTanggalSampai !== '0000-00-00') {
                    $query->whereDate('tanggal', '<=', $filterTanggalSampai);
                }

                if ($filterAkunMasuk !== '' && strtolower($filterAkunMasuk) !== 'all') {
                    $query->where('NamaAkunMasuk', $filterAkunMasuk);
                }

                if ($filterAkunKeluar !== '' && strtolower($filterAkunKeluar) !== 'all') {
                    $query->where('NamaAkunKeluar', $filterAkunKeluar);
                }
            };

            $totalRecords = (clone $baseQuery)->count();

            $filteredQuery = clone $baseQuery;
            $applyFilters($filteredQuery);
            $totalRecordswithFilter = (clone $filteredQuery)->count();

            $saldoQuery = akt_jurnal_in_out::query();
            $applyFilters($saldoQuery);
            $aggregate = $saldoQuery
                ->selectRaw('COALESCE(SUM(kredit), 0) as total_masuk, COALESCE(SUM(debet), 0) as total_keluar')
                ->first();
            $saldo = (int) ($aggregate->total_masuk ?? 0) - (int) ($aggregate->total_keluar ?? 0);

            $records = $filteredQuery
                ->orderBy($columnName, $columnSortOrder)
                ->orderBy('urut', 'asc')
                ->skip($start)
                ->take($rowperpage)
                ->get()
                ->map(function ($item) {
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
                        'keterangan' => $item->keterangan ?? '',
                        'foto' => $bukti,
                        'buktiurl' => $bukti,
                        'periode' => $item->periode ?? '',
                        'tahun' => $item->tahun ?? '',
                        'akun_kas_keluar' => $item->NamaAkunKeluar ?: '-',
                        'akun_kas_masuk' => $item->NamaAkunMasuk ?: '-',
                    ];
                })
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
