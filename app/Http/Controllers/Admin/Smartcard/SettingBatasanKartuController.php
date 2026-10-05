<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
use App\Support\SmartcardExcelExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingBatasanKartuController extends Controller
{
    private const PER_PAGE = 10;

    public function index(Request $request): View
    {
        $isSearch = $request->boolean('search');
        $periode = $isSearch ? $this->normalizePeriode((string) $request->query('periode', '')) : '';
        $batasBelanjaHari = $isSearch ? $this->parseAmount($request->query('batas_belanja_hari', '')) : '';
        $batasCash = $isSearch ? $this->parseAmount($request->query('batas_cash', '')) : '';
        $aktif = $isSearch ? (string) $request->query('aktif', '') : '';
        $editUrut = (int) $request->query('edit_urut', 0);
        $editPeriode = $this->normalizePeriode((string) $request->query('edit_periode', ''));
        $isEdit = false;

        $formPeriode = $isSearch ? $this->formatPeriodeForInput((string) $request->query('periode', '')) : '';
        $formBatasBelanja = $batasBelanjaHari;
        $formBatasCash = $batasCash;
        $formAktif = in_array($aktif, ['0', '1'], true) ? $aktif : '';

        if ($editUrut > 0 || $editPeriode !== '') {
            $editQuery = DB::connection('DATA_MYSQL')->table('sm_batasan');
            if ($editUrut > 0) {
                $editQuery->where('urut', $editUrut);
            } else {
                $editQuery->where('periode', $editPeriode);
            }
            $editRow = $editQuery->first(['urut', 'periode', 'batas_belanja_hari', 'batas_cash', 'aktif']);
            if ($editRow) {
                $isEdit = true;
                $editUrut = (int) ($editRow->urut ?? 0);
                $editPeriode = $this->normalizePeriode((string) ($editRow->periode ?? ''));
                $formPeriode = $this->formatPeriodeForInput((string) ($editRow->periode ?? ''));
                $formBatasBelanja = (string) (int) ($editRow->batas_belanja_hari ?? 0);
                $formBatasCash = (string) (int) ($editRow->batas_cash ?? 0);
                $formAktif = (string) (int) ($editRow->aktif ?? 0);
            } else {
                $editUrut = 0;
                $editPeriode = '';
            }
        }

        return view('admin.smartcard.setting_batasan_kartu.index', [
            'title' => 'smartCARD',
            'mainTitle' => 'Setting Batasan Kartu',
            'dataTitle' => 'Setting Batasan Kartu',
            'batasanRows' => $this->fetchRows($periode, $isSearch),
            'isSearch' => $isSearch,
            'periode' => $formPeriode,
            'batasBelanjaHari' => $formBatasBelanja,
            'batasCash' => $formBatasCash,
            'aktif' => $formAktif,
            'editUrut' => $editUrut,
            'editPeriode' => $editPeriode,
            'isEdit' => $isEdit,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'periode' => ['required', 'string', 'max:20'],
            'batas_belanja_hari' => ['required', 'string', 'max:30'],
            'batas_cash' => ['required', 'string', 'max:30'],
            'aktif' => ['required', 'in:0,1'],
            'urut' => ['nullable', 'integer', 'min:0'],
            'edit_periode' => ['nullable', 'string', 'max:20'],
            'is_edit' => ['nullable', 'in:0,1'],
        ], [
            'periode.required' => 'Periode wajib diisi.',
            'batas_belanja_hari.required' => 'Batas belanja harian wajib diisi.',
            'batas_cash.required' => 'Batas cash wajib diisi.',
            'aktif.required' => 'Status aktif wajib dipilih.',
            'aktif.in' => 'Status aktif tidak valid.',
        ]);

        $periode = $this->normalizePeriode($validated['periode']);
        if ($periode === '') {
            return redirect()
                ->back()
                ->withInput()
                ->with('smartcard_error', 'Format periode tidak valid. Pilih tahun dan bulan.');
        }

        $batasBelanjaHari = $this->parseAmount($validated['batas_belanja_hari']);
        $batasCash = $this->parseAmount($validated['batas_cash']);

        if ($batasBelanjaHari === '' || !is_numeric($batasBelanjaHari)) {
            return redirect()->back()->withInput()->with('smartcard_error', 'Batas belanja harian harus berupa angka.');
        }
        if ($batasCash === '' || !is_numeric($batasCash)) {
            return redirect()->back()->withInput()->with('smartcard_error', 'Batas cash harus berupa angka.');
        }

        $isEdit = (string) ($validated['is_edit'] ?? '0') === '1';
        $urut = (int) ($validated['urut'] ?? 0);
        $oldPeriode = $this->normalizePeriode((string) ($validated['edit_periode'] ?? ''));
        $payload = [
            'periode' => $periode,
            'batas_belanja_hari' => (int) $batasBelanjaHari,
            'batas_cash' => (int) $batasCash,
            'aktif' => (int) $validated['aktif'],
        ];

        if ($isEdit) {
            $updateQuery = DB::connection('DATA_MYSQL')->table('sm_batasan');
            if ($urut > 0) {
                $updateQuery->where('urut', $urut);
            } elseif ($oldPeriode !== '') {
                $updateQuery->where('periode', $oldPeriode);
            } else {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('smartcard_error', 'Data batasan yang diedit tidak valid.');
            }

            if (!$updateQuery->exists()) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('smartcard_error', 'Data batasan yang diedit tidak ditemukan.');
            }

            $periodeTakenQuery = DB::connection('DATA_MYSQL')
                ->table('sm_batasan')
                ->where('periode', $periode);
            if ($urut > 0) {
                $periodeTakenQuery->where('urut', '!=', $urut);
            } elseif ($oldPeriode !== '') {
                $periodeTakenQuery->where('periode', '!=', $oldPeriode);
            }
            if ($periodeTakenQuery->exists()) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('smartcard_error', 'Periode sudah dipakai data lain.');
            }

            $apply = DB::connection('DATA_MYSQL')->table('sm_batasan');
            if ($urut > 0) {
                $apply->where('urut', $urut)->update($payload);
            } else {
                $apply->where('periode', $oldPeriode)->update($payload);
            }

            return redirect()
                ->route('admin.smartcard.setting-batasan-saku.index')
                ->with('smartcard_success', 'Setting batasan kartu berhasil diubah.');
        }

        $exists = DB::connection('DATA_MYSQL')
            ->table('sm_batasan')
            ->where('periode', $periode)
            ->exists();

        if ($exists) {
            return redirect()
                ->back()
                ->withInput()
                ->with('smartcard_error', 'Periode sudah ada. Klik Edit pada baris data untuk mengubah.');
        }

        DB::connection('DATA_MYSQL')->table('sm_batasan')->insert(array_merge($payload, [
            'kelompok_kantin' => null,
            'urut' => null,
        ]));

        return redirect()
            ->route('admin.smartcard.setting-batasan-saku.index')
            ->with('smartcard_success', 'Setting batasan kartu berhasil disimpan.');
    }

    public function export(Request $request): StreamedResponse
    {
        $isSearch = $request->boolean('search');
        $periode = $isSearch ? $this->normalizePeriode((string) $request->query('periode', '')) : '';

        $query = DB::connection('DATA_MYSQL')
            ->table('sm_batasan')
            ->select(['urut', 'periode', 'batas_belanja_hari', 'batas_cash', 'aktif']);

        if ($isSearch && $periode !== '') {
            $query->where('periode', $periode);
        }

        $rows = $query->orderByDesc('periode')->get();

        $exportRows = [];
        $no = 1;
        foreach ($rows as $row) {
            $p = trim((string) ($row->periode ?? ''));
            $periodeLabel = (strlen($p) === 6 && ctype_digit($p))
                ? substr($p, 0, 4) . '-' . substr($p, 4, 2)
                : $p;

            $exportRows[] = [
                $no++,
                $periodeLabel,
                (int) ($row->batas_belanja_hari ?? 0),
                (int) ($row->batas_cash ?? 0),
                ((int) ($row->aktif ?? 0) === 1) ? 'Aktif' : 'Tidak Aktif',
            ];
        }

        return SmartcardExcelExport::download(
            'setting-batasan-kartu-' . date('Ymd-His'),
            ['No', 'Periode', 'Batas Belanja Hari', 'Batas Cash', 'Aktif'],
            $exportRows
        );
    }

    private function fetchRows(string $periode, bool $isSearch): LengthAwarePaginator
    {
        $query = DB::connection('DATA_MYSQL')
            ->table('sm_batasan')
            ->select([
                'urut',
                'periode',
                'batas_belanja_hari',
                'batas_cash',
                'aktif',
            ]);

        if ($isSearch && $periode !== '') {
            $query->where('periode', $periode);
        }

        return $query
            ->orderByDesc('periode')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    private function formatPeriodeForInput(string $value): string
    {
        $normalized = $this->normalizePeriode($value);
        if ($normalized !== '') {
            return substr($normalized, 0, 4) . '-' . substr($normalized, 4, 2);
        }

        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            return $value;
        }

        return '';
    }

    private function normalizePeriode(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) === 6) {
            return $digits;
        }

        return '';
    }

    private function parseAmount(mixed $value): string
    {
        $raw = preg_replace('/[^\d]/', '', (string) $value) ?? '';

        return $raw;
    }
}
