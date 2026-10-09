<?php

namespace App\Http\Controllers\Admin\Pencatatan;

use App\Http\Controllers\Controller;
use App\Models\akun_kas_keluar;
use App\Models\ValidationMessage;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AkunKasKeluarController extends Controller
{
    public string $title = 'Pencatatan Sederhana';
    public string $mainTitle = 'Akun Kas Keluar';
    public string $dataTitle = 'Akun Kas Keluar';

    public function index()
    {
        return view('admin.pencatatan.akun_kas_keluar.index', [
            'title' => $this->title,
            'mainTitle' => $this->mainTitle,
            'dataTitle' => $this->dataTitle,
            'columnsUrl' => route('admin.pencatatan-sederhana.akun-kas-keluar.get-column'),
            'datasUrl' => route('admin.pencatatan-sederhana.akun-kas-keluar.get-data'),
            'storeUrl' => route('admin.pencatatan-sederhana.akun-kas-keluar.store'),
        ]);
    }

    public function getColumn()
    {
        return [
            ['data' => null, 'name' => 'no', 'className' => 'text-center', 'columnType' => 'row'],
            ['data' => 'KodeAkunKeluar', 'name' => 'Kode', 'searchable' => true, 'orderable' => true],
            ['data' => 'NamaAkunKeluar', 'name' => 'Nama Akun Kas Keluar', 'searchable' => true, 'orderable' => true],
        ];
    }

    public function getData(Request $request)
    {
        try {
            $draw = $request->get('draw');
            $start = $request->get('start');
            $rowperpage = $request->get('length');

            $columnIndexArr = $request->get('order', []);
            $columnNameArr = $request->get('columns', []);
            $searchArr = $request->get('search', []);
            $searchValue = $searchArr['value'] ?? '';

            $columnName = 'KodeAkunKeluar';
            $columnSortOrder = 'asc';

            if (!empty($columnIndexArr)) {
                $columnIndex = $columnIndexArr[0]['column'] ?? null;
                if ($columnIndex !== null && !empty($columnNameArr[$columnIndex]['data']) && $columnNameArr[$columnIndex]['data'] !== 'no') {
                    $columnName = $columnNameArr[$columnIndex]['data'];
                    $columnSortOrder = $columnIndexArr[0]['dir'] ?? 'asc';
                }
            }

            $searchable = ['KodeAkunKeluar', 'NamaAkunKeluar', 'NoRekKeluar'];
            $totalRecords = akun_kas_keluar::count();
            $totalRecordswithFilter = akun_kas_keluar::query()
                ->whereAny($searchable, 'like', '%' . $searchValue . '%')
                ->count();

            $records = akun_kas_keluar::query()
                ->orderBy($columnName, $columnSortOrder)
                ->whereAny($searchable, 'like', '%' . $searchValue . '%')
                ->skip($start)
                ->take($rowperpage)
                ->get(['KodeAkunKeluar', 'NamaAkunKeluar'])
                ->toArray();

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalRecordswithFilter,
                'data' => $records,
            ]);
        } catch (\Throwable $e) {
            Log::error('AkunKasKeluar getData failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'draw' => intval($request->get('draw', 0)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'message' => 'Gagal memuat data akun kas keluar',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $kode = trim((string) $request->kode_akun);
        $nama = trim((string) $request->nama_akun);
        $noRek = trim((string) ($request->no_rek ?? ''));

        $validator = Validator::make(
            [
                'kode_akun' => $kode,
                'nama_akun' => $nama,
                'no_rek' => $noRek,
            ],
            [
                'kode_akun' => ['required', 'max:5'],
                'nama_akun' => ['required', 'max:50'],
                'no_rek' => ['nullable', 'max:20'],
            ],
            ValidationMessage::messages(),
            ValidationMessage::attributes()
        );

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        if (akun_kas_keluar::where('KodeAkunKeluar', $kode)->exists()) {
            return response()->json(['message' => 'Kode akun kas keluar sudah ada'], 422);
        }

        if (akun_kas_keluar::where('NamaAkunKeluar', $nama)->exists()) {
            return response()->json(['message' => 'Nama akun kas keluar sudah ada'], 422);
        }

        try {
            DB::connection('DATA_MYSQL')->beginTransaction();
            akun_kas_keluar::create([
                'KodeAkunKeluar' => $kode,
                'NamaAkunKeluar' => $nama,
                'NoRekKeluar' => $noRek,
            ]);
            DB::connection('DATA_MYSQL')->commit();

            return response()->json(['message' => 'Data Akun Kas Keluar telah disimpan']);
        } catch (Exception $e) {
            DB::connection('DATA_MYSQL')->rollBack();
            return response()->json(['message' => 'Data Akun Kas Keluar gagal disimpan', 'error' => $e->getMessage()], 422);
        }
    }
}
