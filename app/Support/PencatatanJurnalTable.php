<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class PencatatanJurnalTable
{
    private static ?string $resolved = null;

    public static function name(): string
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $connection = 'DATA_MYSQL';
        foreach (['akt_jurnal', 'akt_jurnal_in_out'] as $candidate) {
            if (Schema::connection($connection)->hasTable($candidate)) {
                return self::$resolved = $candidate;
            }
        }

        return self::$resolved = 'akt_jurnal';
    }

    public static function hasAkunMasukColumn(): bool
    {
        return Schema::connection('DATA_MYSQL')->hasColumn(self::name(), 'NamaAkunMasuk');
    }

    public static function hasAkunKeluarColumn(): bool
    {
        return Schema::connection('DATA_MYSQL')->hasColumn(self::name(), 'NamaAkunKeluar');
    }
}
