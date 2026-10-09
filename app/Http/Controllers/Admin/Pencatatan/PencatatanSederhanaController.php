<?php

namespace App\Http\Controllers\Admin\Pencatatan;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PencatatanSederhanaController extends Controller
{
    public function kasMasuk(): View
    {
        return $this->page('Kas Masuk');
    }

    public function kasKeluar(): View
    {
        return $this->page('Kas Keluar');
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
