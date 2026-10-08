<?php

namespace App\Http\Controllers\Admin\Pencatatan;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PencatatanSederhanaController extends Controller
{
    public function akunKasMasuk(): View
    {
        return $this->page('Akun Kas Masuk');
    }

    public function akunKasKeluar(): View
    {
        return $this->page('Akun Kas Keluar');
    }

    public function kasMasuk(): View
    {
        return $this->page('Kas Masuk');
    }

    public function kasKeluar(): View
    {
        return $this->page('Kas Keluar');
    }

    public function cekPencatatan(): View
    {
        return $this->page('Cek Pencatatan');
    }

    public function rekapExportExcel(): View
    {
        return $this->page('Rekap Export Excel');
    }

    private function page(string $mainTitle): View
    {
        return view('admin.pencatatan.index', [
            'title' => 'Pencatatan Sederhana',
            'mainTitle' => $mainTitle,
            'dataTitle' => $mainTitle,
        ]);
    }
}
