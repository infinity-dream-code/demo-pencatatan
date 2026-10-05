<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kuitansi Top Up {{ $transNo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h2 { margin: 0 0 4px; font-size: 14px; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 4px 0; vertical-align: top; }
        .label { width: 32%; color: #444; }
        .num { text-align: right; font-weight: bold; }
        .box { border: 1px solid #ccc; padding: 10px; margin-top: 10px; }
        .foot { margin-top: 18px; text-align: right; }
        .total { border-top: 1px solid #999; margin-top: 6px; padding-top: 6px; }
    </style>
</head>
<body>
    <h2>{{ $sekolahNama ?? 'Muallimaat' }}</h2>
    <div class="muted">Kuitansi TOP UP Uang Saku</div>

    <div class="box">
        <table>
            <tr><td class="label">No Transaksi</td><td>{{ $transNo }}</td></tr>
            <tr><td class="label">Tanggal</td><td>{{ \Illuminate\Support\Carbon::parse($trxDate)->format('d/m/Y H:i') }}</td></tr>
            <tr><td class="label">NIS</td><td>{{ $nis }}</td></tr>
            <tr><td class="label">Nama</td><td>{{ $nama }}</td></tr>
            <tr><td class="label">Kelas</td><td>{{ $kelas ?: '-' }}</td></tr>
            <tr><td class="label">Metode</td><td>{{ $metode }}</td></tr>
            <tr><td class="label">Keterangan</td><td>{{ $note ?: '-' }}</td></tr>
        </table>
    </div>

    <table>
        <tr>
            <td>Nominal TOP UP</td>
            <td class="num">Rp {{ number_format((int) $nominal, 0, ',', '.') }}</td>
        </tr>
        <tr class="total">
            <td>Masuk saldo</td>
            <td class="num">Rp {{ number_format((int) ($saldoDidapat ?? $nominal), 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="foot">
        <div>Petugas</div>
        <br><br>
        <strong>{{ $teller }}</strong>
    </div>
</body>
</html>
