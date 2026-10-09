<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class akun_kas_masuk extends Model
{
    protected $connection = "DATA_MYSQL";

    public $timestamps = false;

    public $incrementing = false;

    protected $table = "akun_kas_masuk";

    protected $primaryKey = "KodeAkunMasuk";

    protected $keyType = "string";

    protected $fillable = [
        "KodeAkunMasuk",
        "NamaAkunMasuk",
        "NoRekMasuk",
    ];
}
