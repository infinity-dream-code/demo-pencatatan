<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Keluar Uang Saku</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111;
            margin: 0;
            padding: 20px 24px;
        }
        .header-right {
            text-align: right;
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 6px;
        }
        .title {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            margin: 8px 0 4px;
            text-decoration: underline;
        }
        .subtitle {
            text-align: center;
            font-size: 10px;
            margin-bottom: 14px;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        table.data th,
        table.data td {
            border: 1px solid #111;
            padding: 5px 6px;
        }
        table.data th {
            background: #f3f4f6;
            font-weight: bold;
        }
        table.data td.num { text-align: right; }
        .totals {
            margin-top: 8px;
            font-size: 10px;
        }
        .totals strong { font-size: 11px; }
    </style>
</head>
<body>
    <div class="header-right">{{ $sekolahNama ?? 'Raudhatul Quran' }}</div>
    <div class="title">REKAP KELUAR UANG SAKU</div>
    @php
        $dari = trim((string) ($filters['dari_tanggal'] ?? ''));
        $sampai = trim((string) ($filters['sampai_tanggal'] ?? ''));
        $dariFmt = ($dari !== '' && $dari !== '0000-00-00') ? $dari : '0000-00-00';
        $sampaiFmt = ($sampai !== '' && $sampai !== '0000-00-00') ? $sampai : '0000-00-00';
    @endphp
    <div class="subtitle">{{ $dariFmt }} s.d {{ $sampaiFmt }}</div>

    <table class="data">
        <thead>
            <tr>
                <th style="width:28px;">No</th>
                <th style="width:70px;">Kelas</th>
                <th style="width:28px;">L/P</th>
                <th style="width:80px;">Lokasi</th>
                <th style="width:80px;">NIS</th>
                <th>Nama Siswa</th>
                <th style="width:100px;">Tgl Transaksi</th>
                <th style="width:80px;">DEBET</th>
                <th style="width:80px;">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse (($rows ?? collect()) as $i => $row)
                <tr>
                    <td style="text-align:center;">{{ $i + 1 }}</td>
                    <td>{{ $row->kelas ?? '—' }}</td>
                    <td style="text-align:center;">{{ $row->gender ?? '—' }}</td>
                    <td>{{ $row->lokasi ?? '—' }}</td>
                    <td>{{ $row->nis ?? '—' }}</td>
                    <td>{{ $row->nama ?? '—' }}</td>
                    <td>
                        @if (!empty($row->tgl_transaksi))
                            {{ \Illuminate\Support\Carbon::parse($row->tgl_transaksi)->format('Y-m-d H:i:s') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="num">{{ number_format((int) ($row->debet ?? 0), 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((int) ($row->saldo ?? 0), 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align:center;padding:16px;">Tidak ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        Total DEBET: <strong>{{ number_format((int) ($totals['debet'] ?? 0), 0, ',', '.') }}</strong>
    </div>

    <div style="margin-top:24px;text-align:right;font-size:10px;">
        Raudhatul Quran, {{ now()->format('Y-m-d') }}
    </div>
</body>
</html>
