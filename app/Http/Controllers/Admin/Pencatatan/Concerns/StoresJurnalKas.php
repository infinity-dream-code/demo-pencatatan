<?php

namespace App\Http\Controllers\Admin\Pencatatan\Concerns;

use App\Models\akt_jurnal;
use App\Support\PencatatanJurnalTable;
use Illuminate\Support\Carbon;

trait StoresJurnalKas
{
    protected function normalizeDatetime(string $tanggal): string
    {
        $tanggal = trim(str_replace('T', ' ', $tanggal));
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $tanggal)) {
            $tanggal .= ':00';
        }

        return $tanggal;
    }

    protected function periodeFromDate(string $tanggal): array
    {
        try {
            $dt = Carbon::parse($this->normalizeDatetime($tanggal));
        } catch (\Throwable) {
            $dt = Carbon::now();
        }

        return [
            'tahun' => $dt->format('Y'),
            'periode' => $dt->format('Ym'),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function insertJurnal(array $extra): akt_jurnal
    {
        $payload = array_merge([
            'no_tran' => null,
            'no_ref' => null,
            'kode_perkiraan' => null,
        ], $extra);

        if (!PencatatanJurnalTable::hasAkunMasukColumn()) {
            unset($payload['NamaAkunMasuk']);
        }
        if (!PencatatanJurnalTable::hasAkunKeluarColumn()) {
            unset($payload['NamaAkunKeluar']);
        }

        return akt_jurnal::query()->create($payload);
    }
}
