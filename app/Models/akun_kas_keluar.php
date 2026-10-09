<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class akun_kas_keluar extends Model
{
    protected $connection = "DATA_MYSQL";

    public $timestamps = false;

    public $incrementing = false;

    protected $table = "akun_kas_keluar";

    protected $primaryKey = "KodeAkunKeluar";

    protected $keyType = "string";

    protected $fillable = [
        "KodeAkunKeluar",
        "NamaAkunKeluar",
        "NoRekKeluar",
    ];
}
