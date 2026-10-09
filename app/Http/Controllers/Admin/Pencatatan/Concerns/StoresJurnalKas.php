<?php

namespace App\Http\Controllers\Admin\Pencatatan\Concerns;

use App\Models\akt_jurnal;
use App\Support\CloudinaryUpload;
use App\Support\PencatatanJurnalTable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;

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

    /**
     * Resolve bukti URL: upload file to Cloudinary folder ponpes_markaz, or keep pasted URL.
     */
    protected function resolveBuktiUrl(Request $request): string
    {
        if ($request->hasFile('bukti_foto')) {
            $file = $request->file('bukti_foto');
            if (!$file || !$file->isValid()) {
                throw new RuntimeException('File foto bukti tidak valid.');
            }

            $uploaded = CloudinaryUpload::image($file);

            return $uploaded['secure_url'];
        }

        $buktiurl = trim((string) $request->input('buktiurl', ''));

        return $buktiurl !== '' ? $buktiurl : '-';
    }
}
