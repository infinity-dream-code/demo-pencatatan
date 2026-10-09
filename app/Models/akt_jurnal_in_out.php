<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class akt_jurnal_in_out extends Model
{
    protected $connection = "DATA_MYSQL";

    public $timestamps = false;

    protected $table = "akt_jurnal_in_out";

    protected $primaryKey = "urut";

    protected $fillable = [
        "no_tran",
        "no_ref",
        "tanggal",
        "no_bukti",
        "keterangan",
        "kode_perkiraan",
        "debet",
        "kredit",
        "tahun",
        "periode",
        "buktiurl",
        "NamaAkunMasuk",
        "NamaAkunKeluar",
    ];
}
