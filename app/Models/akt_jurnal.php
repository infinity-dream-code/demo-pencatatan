<?php

namespace App\Models;

use App\Support\PencatatanJurnalTable;
use Illuminate\Database\Eloquent\Model;

class akt_jurnal extends Model
{
    protected $connection = "DATA_MYSQL";

    public $timestamps = false;

    protected $primaryKey = "urut";

    protected $guarded = [];

    public function getTable()
    {
        return PencatatanJurnalTable::name();
    }
}
