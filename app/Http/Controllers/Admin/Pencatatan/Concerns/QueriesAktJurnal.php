<?php

namespace App\Http\Controllers\Admin\Pencatatan\Concerns;

use App\Models\akun_kas_keluar;
use App\Models\akun_kas_masuk;
use App\Models\akt_jurnal;
use App\Support\PencatatanJurnalTable;
use Illuminate\Database\Eloquent\Builder;

trait QueriesAktJurnal
{
    /** @var array<string, string>|null */
    private static ?array $akunMasukMap = null;

    /** @var array<string, string>|null */
    private static ?array $akunKeluarMap = null;

    protected function jurnalBaseQuery(): Builder
    {
        $select = [
            'urut',
            'tanggal',
            'no_bukti',
            'keterangan',
            'debet',
            'kredit',
            'tahun',
            'periode',
            'buktiurl',
        ];

        if (PencatatanJurnalTable::hasAkunMasukColumn()) {
            $select[] = 'NamaAkunMasuk';
        }
        if (PencatatanJurnalTable::hasAkunKeluarColumn()) {
            $select[] = 'NamaAkunKeluar';
        }

        return akt_jurnal::query()->select($select);
    }

    /**
     * @param  array<string, string>  $filters
     */
    protected function applyJurnalFilters(Builder $query, array $filters, string $searchValue = ''): void
    {
        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                $q->where('no_bukti', 'like', '%' . $searchValue . '%')
                    ->orWhere('keterangan', 'like', '%' . $searchValue . '%')
                    ->orWhere('periode', 'like', '%' . $searchValue . '%')
                    ->orWhere('tahun', 'like', '%' . $searchValue . '%');

                if (PencatatanJurnalTable::hasAkunMasukColumn()) {
                    $q->orWhere('NamaAkunMasuk', 'like', '%' . $searchValue . '%');
                }
                if (PencatatanJurnalTable::hasAkunKeluarColumn()) {
                    $q->orWhere('NamaAkunKeluar', 'like', '%' . $searchValue . '%');
                }
            });
        }

        $filterPeriode = trim((string) ($filters['periode'] ?? ''));
        $filterNoBukti = trim((string) ($filters['no_bukti'] ?? ''));
        $filterKeterangan = trim((string) ($filters['keterangan'] ?? ''));
        $filterTanggalDari = trim((string) ($filters['tanggal_dari'] ?? ''));
        $filterTanggalSampai = trim((string) ($filters['tanggal_sampai'] ?? ''));
        $filterAkunMasuk = trim((string) ($filters['akun_masuk'] ?? ''));
        $filterAkunKeluar = trim((string) ($filters['akun_keluar'] ?? ''));

        if ($filterPeriode !== '' && strtolower($filterPeriode) !== 'all') {
            $query->where('periode', $filterPeriode);
        }

        if ($filterNoBukti !== '') {
            $query->where('no_bukti', 'like', '%' . $filterNoBukti . '%');
        }

        if ($filterKeterangan !== '') {
            $query->where('keterangan', 'like', '%' . $filterKeterangan . '%');
        }

        if ($filterTanggalDari !== '' && $filterTanggalDari !== '0000-00-00') {
            $query->whereDate('tanggal', '>=', $filterTanggalDari);
        }

        if ($filterTanggalSampai !== '' && $filterTanggalSampai !== '0000-00-00') {
            $query->whereDate('tanggal', '<=', $filterTanggalSampai);
        }

        if ($filterAkunMasuk !== '' && strtolower($filterAkunMasuk) !== 'all') {
            if (PencatatanJurnalTable::hasAkunMasukColumn()) {
                $query->where('NamaAkunMasuk', $filterAkunMasuk);
            } else {
                $kode = $this->kodeAkunMasukByNama($filterAkunMasuk);
                if ($kode !== null) {
                    $query->where('no_bukti', $kode)->where('kredit', '>', 0);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        }

        if ($filterAkunKeluar !== '' && strtolower($filterAkunKeluar) !== 'all') {
            if (PencatatanJurnalTable::hasAkunKeluarColumn()) {
                $query->where('NamaAkunKeluar', $filterAkunKeluar);
            } else {
                $kode = $this->kodeAkunKeluarByNama($filterAkunKeluar);
                if ($kode !== null) {
                    $query->where('no_bukti', $kode)->where('debet', '>', 0);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        }
    }

    /**
     * @param  array<string, string>  $filters
     */
    protected function calculateJurnalSaldo(array $filters, string $searchValue = ''): int
    {
        $query = akt_jurnal::query();
        $this->applyJurnalFilters($query, $filters, $searchValue);
        $aggregate = $query
            ->selectRaw('COALESCE(SUM(kredit), 0) as total_masuk, COALESCE(SUM(debet), 0) as total_keluar')
            ->first();

        return (int) ($aggregate->total_masuk ?? 0) - (int) ($aggregate->total_keluar ?? 0);
    }

    protected function mapJurnalRow(object $item, ?int $saldo = null): array
    {
        $bukti = trim((string) ($item->buktiurl ?? ''));
        if ($bukti === '' || $bukti === '-') {
            $bukti = '-';
        }

        $namaMasuk = $item->NamaAkunMasuk ?? null;
        $namaKeluar = $item->NamaAkunKeluar ?? null;
        $noBukti = trim((string) ($item->no_bukti ?? ''));

        if (!PencatatanJurnalTable::hasAkunMasukColumn() && (int) ($item->kredit ?? 0) > 0 && $noBukti !== '') {
            $namaMasuk = $this->akunMasukMap()[$noBukti] ?? $namaMasuk;
        }
        if (!PencatatanJurnalTable::hasAkunKeluarColumn() && (int) ($item->debet ?? 0) > 0 && $noBukti !== '') {
            $namaKeluar = $this->akunKeluarMap()[$noBukti] ?? $namaKeluar;
        }

        $row = [
            'item_id' => $item->urut,
            'urut' => $item->urut,
            'tanggal' => $item->tanggal,
            'no_bukti' => $noBukti,
            'nominal_keluar' => (int) ($item->debet ?? 0),
            'nominal_masuk' => (int) ($item->kredit ?? 0),
            'keterangan' => $item->keterangan ?? '',
            'foto' => $bukti,
            'buktiurl' => $bukti,
            'periode' => $item->periode ?? '',
            'tahun' => $item->tahun ?? '',
            'akun_kas_keluar' => $namaKeluar ?: '-',
            'akun_kas_masuk' => $namaMasuk ?: '-',
        ];

        if ($saldo !== null) {
            $row['saldo'] = $saldo;
        }

        return $row;
    }

    /** @return array<string, string> */
    private function akunMasukMap(): array
    {
        if (self::$akunMasukMap === null) {
            self::$akunMasukMap = akun_kas_masuk::query()
                ->pluck('NamaAkunMasuk', 'KodeAkunMasuk')
                ->all();
        }

        return self::$akunMasukMap;
    }

    /** @return array<string, string> */
    private function akunKeluarMap(): array
    {
        if (self::$akunKeluarMap === null) {
            self::$akunKeluarMap = akun_kas_keluar::query()
                ->pluck('NamaAkunKeluar', 'KodeAkunKeluar')
                ->all();
        }

        return self::$akunKeluarMap;
    }

    private function kodeAkunMasukByNama(string $nama): ?string
    {
        foreach ($this->akunMasukMap() as $kode => $label) {
            if ($label === $nama) {
                return (string) $kode;
            }
        }

        return null;
    }

    private function kodeAkunKeluarByNama(string $nama): ?string
    {
        foreach ($this->akunKeluarMap() as $kode => $label) {
            if ($label === $nama) {
                return (string) $kode;
            }
        }

        return null;
    }

    protected function jurnalSortMap(): array
    {
        $map = [
            'tanggal' => 'tanggal',
            'no_bukti' => 'no_bukti',
            'nominal_keluar' => 'debet',
            'nominal_masuk' => 'kredit',
            'keterangan' => 'keterangan',
            'periode' => 'periode',
            'tahun' => 'tahun',
        ];

        if (PencatatanJurnalTable::hasAkunKeluarColumn()) {
            $map['akun_kas_keluar'] = 'NamaAkunKeluar';
        }
        if (PencatatanJurnalTable::hasAkunMasukColumn()) {
            $map['akun_kas_masuk'] = 'NamaAkunMasuk';
        }

        return $map;
    }
}
