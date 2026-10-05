<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class SmartcardExcelExport
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|null>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $filename = preg_replace('/\.xlsx?$/i', '', $filename) ?: 'export';
        $filename .= '.xls';

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            // UTF-8 BOM agar Excel Windows membaca karakter dengan benar
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');

            foreach ($rows as $row) {
                $line = [];
                foreach ($row as $cell) {
                    if ($cell === null) {
                        $line[] = '';
                    } elseif (is_float($cell) || is_int($cell)) {
                        $line[] = $cell;
                    } else {
                        $line[] = (string) $cell;
                    }
                }
                fputcsv($out, $line, ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }
}
