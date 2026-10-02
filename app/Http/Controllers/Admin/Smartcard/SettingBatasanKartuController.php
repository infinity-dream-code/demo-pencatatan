<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

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

        return view('admin.smartcard.setting_batasan_kartu.index', [
            'title' => 'smartCARD',
            'mainTitle' => 'Setting Batasan Kartu',
            'dataTitle' => 'Setting Batasan Kartu',
            'batasanRows' => $this->fetchRows($periode, $isSearch),
            'isSearch' => $isSearch,
            'periode' => $isSearch ? $this->formatPeriodeForInput((string) $request->query('periode', '')) : '',
            'batasBelanjaHari' => $batasBelanjaHari,
            'batasCash' => $batasCash,
            'aktif' => in_array($aktif, ['0', '1'], true) ? $aktif : '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'periode' => ['required', 'string', 'max:20'],
            'batas_belanja_hari' => ['required', 'string', 'max:30'],
            'batas_cash' => ['required', 'string', 'max:30'],
            'aktif' => ['required', 'in:0,1'],
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

        $exists = DB::connection('DATA_MYSQL')
            ->table('sm_batasan')
            ->where('periode', $periode)
            ->exists();

        if ($exists) {
            return redirect()
                ->back()
                ->withInput()
                ->with('smartcard_error', 'Periode sudah ada. Gunakan Lihat untuk melihat data atau gunakan periode lain.');
        }

        DB::connection('DATA_MYSQL')->table('sm_batasan')->insert([
            'periode' => $periode,
            'batas_belanja_hari' => (int) $batasBelanjaHari,
            'batas_cash' => (int) $batasCash,
            'aktif' => (int) $validated['aktif'],
            'kelompok_kantin' => null,
            'urut' => null,
        ]);

        return redirect()
            ->route('admin.smartcard.setting-batasan-saku.index')
            ->with('smartcard_success', 'Setting batasan kartu berhasil disimpan.');
    }

    private function fetchRows(string $periode, bool $isSearch): LengthAwarePaginator
    {
        $query = DB::connection('DATA_MYSQL')
            ->table('sm_batasan')
            ->select([
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
