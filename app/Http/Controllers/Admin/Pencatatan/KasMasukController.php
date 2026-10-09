<?php

namespace App\Http\Controllers\Admin\Pencatatan;

use App\Http\Controllers\Admin\Pencatatan\Concerns\StoresJurnalKas;
use App\Http\Controllers\Controller;
use App\Models\akun_kas_masuk;
use App\Models\akt_jurnal;
use App\Models\ValidationMessage;
use App\Support\PencatatanJurnalTable;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class KasMasukController extends Controller
{
    use StoresJurnalKas;

    public string $title = 'Pencatatan Sederhana';
    public string $mainTitle = 'Kas Masuk';
    public string $dataTitle = 'Kas Masuk';

    public function index()
    {
        $akunMasuk = collect();
        try {
            if (Schema::connection('DATA_MYSQL')->hasTable('akun_kas_masuk')) {
                $akunMasuk = akun_kas_masuk::query()
                    ->orderBy('KodeAkunMasuk')
                    ->get(['KodeAkunMasuk', 'NamaAkunMasuk']);
            }
        } catch (\Throwable $e) {
            Log::warning('KasMasuk index lookup failed', ['message' => $e->getMessage()]);
        }

        return view('admin.pencatatan.kas_masuk.index', [
            'title' => $this->title,
            'mainTitle' => $this->mainTitle,
            'dataTitle' => $this->dataTitle,
            'akunMasuk' => $akunMasuk,
            'columnsUrl' => route('admin.pencatatan-sederhana.kas-masuk.get-column'),
            'datasUrl' => route('admin.pencatatan-sederhana.kas-masuk.get-data'),
            'storeUrl' => route('admin.pencatatan-sederhana.kas-masuk.store'),
        ]);
    }

    public function getColumn()
    {
        return [
            ['data' => null, 'name' => 'no', 'className' => 'text-center', 'columnType' => 'row'],
            ['data' => 'tanggal', 'name' => 'Tanggal', 'searchable' => true, 'orderable' => true],
            ['data' => 'no_bukti', 'name' => 'No Bukti', 'searchable' => true, 'orderable' => true],
            [
                'data' => 'kredit',
                'name' => 'Nominal Kas Masuk',
                'searchable' => false,
                'orderable' => true,
                'columnType' => 'currency',
                'className' => 'text-end',
            ],
            ['data' => 'keterangan', 'name' => 'Keterangan', 'searchable' => true, 'orderable' => true],
            ['data' => 'NamaAkunMasuk', 'name' => 'Akun Kas Masuk', 'searchable' => true, 'orderable' => true],
            ['data' => 'periode', 'name' => 'Periode', 'searchable' => true, 'orderable' => true],
        ];
    }

    public function getData(Request $request)
    {
        try {
            $draw = (int) $request->get('draw', 0);
            $start = (int) $request->get('start', 0);
            $rowperpage = (int) $request->get('length', 10);
            $searchArr = $request->get('search', []);
            $searchValue = trim((string) ($searchArr['value'] ?? ''));

            $columnIndexArr = $request->get('order', []);
            $columnNameArr = $request->get('columns', []);
            $columnName = 'tanggal';
            $columnSortOrder = 'desc';
            if (!empty($columnIndexArr)) {
                $columnIndex = (int) ($columnIndexArr[0]['column'] ?? 0);
                $columnSortOrder = strtolower((string) ($columnIndexArr[0]['dir'] ?? 'desc'));
                $requested = (string) ($columnNameArr[$columnIndex]['data'] ?? 'tanggal');
                if ($requested !== 'no' && $requested !== '') {
                    $columnName = $requested === 'kredit' ? 'kredit' : $requested;
                }
            }

            $baseQuery = akt_jurnal::query()->where('kredit', '>', 0);
            $searchable = ['no_bukti', 'keterangan', 'periode', 'tahun'];
            if (PencatatanJurnalTable::hasAkunMasukColumn()) {
                $searchable[] = 'NamaAkunMasuk';
            }

            $applySearch = function ($query) use ($searchValue, $searchable) {
                if ($searchValue === '') {
                    return;
                }
                $query->where(function ($q) use ($searchValue, $searchable) {
                    foreach ($searchable as $col) {
                        $q->orWhere($col, 'like', '%' . $searchValue . '%');
                    }
                });
            };

            $totalRecords = (clone $baseQuery)->count();
            $filteredQuery = clone $baseQuery;
            $applySearch($filteredQuery);
            $totalFiltered = (clone $filteredQuery)->count();

            $select = ['urut', 'tanggal', 'no_bukti', 'keterangan', 'kredit', 'periode', 'tahun'];
            if (PencatatanJurnalTable::hasAkunMasukColumn()) {
                $select[] = 'NamaAkunMasuk';
            }

            $records = $filteredQuery
                ->orderBy($columnName, in_array($columnSortOrder, ['asc', 'desc'], true) ? $columnSortOrder : 'desc')
                ->orderBy('urut', 'desc')
                ->skip($start)
                ->take($rowperpage)
                ->get($select)
                ->map(function ($item) {
                    $row = $item->toArray();
                    $row['NamaAkunMasuk'] = $row['NamaAkunMasuk'] ?? '-';
                    $row['kredit'] = (int) ($row['kredit'] ?? 0);
                    return $row;
                })
                ->toArray();

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalFiltered,
                'data' => $records,
            ]);
        } catch (\Throwable $e) {
            Log::error('KasMasuk getData failed', ['message' => $e->getMessage()]);
            return response()->json([
                'draw' => (int) $request->get('draw', 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $tanggal = $this->normalizeDatetime(trim((string) $request->input('tanggal', '')));
        $kodeAkun = trim((string) $request->input('kode_akun', ''));
        $keterangan = trim((string) $request->input('keterangan', ''));
        $nominal = (int) preg_replace('/\D/', '', (string) $request->input('nominal', '0'));
        $buktiurl = trim((string) $request->input('buktiurl', ''));

        $validator = Validator::make(
            compact('tanggal', 'kode_akun', 'keterangan', 'nominal'),
            [
                'tanggal' => ['required', 'date'],
                'kode_akun' => ['required', 'max:5'],
                'keterangan' => ['required', 'max:255'],
                'nominal' => ['required', 'integer', 'min:1'],
            ],
            ValidationMessage::messages(),
            ValidationMessage::attributes()
        );

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $akun = akun_kas_masuk::query()->where('KodeAkunMasuk', $kodeAkun)->first();
        if (!$akun) {
            return response()->json(['message' => 'Akun kas masuk tidak ditemukan'], 422);
        }

        $period = $this->periodeFromDate($tanggal);

        try {
            DB::connection('DATA_MYSQL')->beginTransaction();
            $this->insertJurnal([
                'tanggal' => $tanggal,
                'no_bukti' => $kodeAkun,
                'keterangan' => $keterangan,
                'debet' => 0,
                'kredit' => $nominal,
                'tahun' => $period['tahun'],
                'periode' => $period['periode'],
                'buktiurl' => $buktiurl !== '' ? $buktiurl : '-',
                'NamaAkunMasuk' => $akun->NamaAkunMasuk,
                'NamaAkunKeluar' => null,
            ]);
            DB::connection('DATA_MYSQL')->commit();

            return response()->json(['message' => 'Kas masuk berhasil disimpan (kredit)']);
        } catch (Exception $e) {
            DB::connection('DATA_MYSQL')->rollBack();
            return response()->json(['message' => 'Kas masuk gagal disimpan', 'error' => $e->getMessage()], 422);
        }
    }
}
