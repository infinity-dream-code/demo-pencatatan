<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Transaksi Siswa — {{ $siswa->nis ?? '' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h2 { margin: 0 0 4px; font-size: 16px; }
        .meta { margin-bottom: 14px; }
        .meta td { padding: 2px 8px 2px 0; }
        table.grid { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.grid th, table.grid td { border: 1px solid #888; padding: 4px 6px; }
        table.grid th { background: #eee; }
        .text-end { text-align: right; }
        .muted { color: #666; font-size: 10px; }
    </style>
</head>
<body>
    <h2>CETAK TRANSAKSI SISWA</h2>
    <div class="muted">PENGELUARAN UANG SAKU — {{ $printedAt->format('d/m/Y H:i') }} — {{ $teller }}</div>

    <table class="meta">
        <tr><td>NIS</td><td>: <strong>{{ $siswa->nis }}</strong></td></tr>
        <tr><td>Nama</td><td>: <strong>{{ $siswa->nama }}</strong></td></tr>
        <tr><td>Kelas</td><td>: {{ $siswa->kelas }} / {{ $siswa->kelompok }} / {{ $siswa->jenjang }}</td></tr>
        <tr><td>Saldo</td><td>: <strong>Rp {{ number_format((int)$saldo, 0, ',', '.') }}</strong></td></tr>
    </table>

    <table class="grid">
        <thead>
        <tr>
            <th>Tanggal</th>
            <th>METODE</th>
            <th class="text-end">MASUK</th>
            <th class="text-end">KELUAR</th>
            <th>Keterangan</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($tranRows as $row)
            <tr>
                <td>{{ $row->tanggal }}</td>
                <td>{{ $row->metode }}</td>
                <td class="text-end">{{ number_format((int)$row->kredit, 0, ',', '.') }}</td>
                <td class="text-end">{{ number_format((int)$row->debet, 0, ',', '.') }}</td>
                <td>{{ $row->keterangan }}</td>
            </tr>
        @empty
            <tr><td colspan="5" style="text-align:center;">Tidak ada transaksi</td></tr>
        @endforelse
        </tbody>
        <tfoot>
        <tr>
            <th colspan="2" class="text-end">Total</th>
            <th class="text-end">{{ number_format((int)$totalMasuk, 0, ',', '.') }}</th>
            <th class="text-end">{{ number_format((int)$totalKeluar, 0, ',', '.') }}</th>
            <th></th>
        </tr>
        </tfoot>
    </table>
</body>
</html>
