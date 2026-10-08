<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo uang saku Muallimaat: dari v_saldo_saku (bukan v_saldo_va).
 */
class SmartcardSaldo
{
    public const VIEW = 'v_saldo_saku';

    public static function for(int $custid): int
    {
        return (int) (self::map([$custid])[$custid] ?? 0);
    }

    /**
     * @param  list<int>  $custids
     * @return array<int, int>
     */
    public static function map(array $custids): array
    {
        $custids = array_values(array_unique(array_filter(array_map('intval', $custids))));
        if ($custids === []) {
            return [];
        }

        try {
            $rows = DB::connection('DATA_MYSQL')
                ->table(self::VIEW)
                ->whereIn('CUSTID', $custids)
                ->get(['CUSTID', 'SALDO']);

            $map = [];
            foreach ($rows as $row) {
                $map[(int) $row->CUSTID] = (int) ($row->SALDO ?? 0);
            }

            // Jika semua CUSTID ketemu di view, pakai hasil view (meski saldo 0)
            if ($map !== [] || self::hasView()) {
                return $map;
            }
        } catch (\Throwable) {
        }

        $rows = DB::connection('DATA_MYSQL')
            ->table('sccttran')
            ->whereIn('CUSTID', $custids)
            ->groupBy('CUSTID')
            ->selectRaw('CUSTID, CAST(COALESCE(SUM(KREDIT),0) AS SIGNED) - CAST(COALESCE(SUM(DEBET),0) AS SIGNED) as saldo')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->CUSTID] = (int) ($row->saldo ?? 0);
        }

        return $map;
    }

    public static function hasView(): bool
    {
        try {
            if (Schema::connection('DATA_MYSQL')->hasTable(self::VIEW)) {
                return true;
            }
        } catch (\Throwable) {
        }

        // hasTable kadang tidak mendeteksi VIEW — coba query langsung
        try {
            DB::connection('DATA_MYSQL')->table(self::VIEW)->limit(1)->get(['CUSTID']);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
