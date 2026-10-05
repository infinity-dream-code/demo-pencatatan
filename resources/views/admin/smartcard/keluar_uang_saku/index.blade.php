@extends('layouts.admin_new')
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{ $dataTitle ?? 'PENGELUARAN UANG SAKU' }}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a>
        </li>
        <li class="breadcrumb-item">smartCARD</li>
        <li class="breadcrumb-item active">Keluar Uang Saku</li>
    </ul>

    @if (session('smartcard_success'))
        <div class="alert alert-success">{{ session('smartcard_success') }}</div>
    @endif
    @if (session('smartcard_error'))
        <div class="alert alert-danger">{{ session('smartcard_error') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header bg-primary">
            <h5 class="mb-0 text-white">PENGELUARAN UANG SAKU</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.smartcard.keluar-uang-saku.store') }}" id="formKeluar">
                @csrf
                <input type="hidden" name="custid" id="custid" value="{{ (int) ($custid ?? 0) }}">

                <div class="row g-3 align-items-end">
                    <div class="col-md-2 position-relative">
                        <label class="form-label">NIS</label>
                        <input type="text" class="form-control" id="nisInput" name="nis_display"
                               value="{{ $nis ?? '' }}" autocomplete="off" placeholder="Min. 3 karakter">
                        <div id="nisList" class="list-group position-absolute w-100 shadow border bg-white"
                             style="display:none;z-index:1050;max-height:240px;overflow:auto;top:calc(100% + 2px);background:#fff !important;"></div>
                    </div>
                    <div class="col-md-3 position-relative">
                        <label class="form-label">NAMA</label>
                        <input type="text" class="form-control" id="namaInput" name="nama_display"
                               value="{{ $nama ?? '' }}" autocomplete="off" placeholder="Min. 3 karakter">
                        <div id="namaList" class="list-group position-absolute w-100 shadow border bg-white"
                             style="display:none;z-index:1050;max-height:240px;overflow:auto;top:calc(100% + 2px);background:#fff !important;"></div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">SALDO</label>
                        <input type="text" class="form-control fw-bold text-primary" id="saldoBox"
                               value="{{ number_format((int) ($saldo ?? 0), 0, ',', '.') }}" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">NOMINAL</label>
                        <input type="text" class="form-control fw-bold" name="nominal" id="nominalInput"
                               inputmode="numeric" value="0" autocomplete="off">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Keterangan</label>
                        <input type="text" class="form-control" name="keterangan" id="ketInput"
                               value="{{ $keterangan ?? '' }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tgl Manual</label>
                        <input type="date" class="form-control" name="tanggal_manual" id="tglInput"
                               value="{{ ($tanggalManual ?? '') !== '0000-00-00' ? ($tanggalManual ?? '') : '' }}">
                    </div>
                    <div class="col-12 d-flex gap-2 flex-wrap align-items-center">
                        <button type="button" class="btn btn-outline-primary" id="btnCari">CARI</button>
                        <button type="submit" class="btn btn-danger" id="btnCashKeluar" disabled>Cash Keluar</button>
                        <button type="submit" class="btn btn-outline-secondary" form="formCetak"
                                @disabled((int)($custid ?? 0) <= 0)>Cetak Transaksi Siswa</button>
                        <a href="{{ $exportUrl ?? route('admin.smartcard.keluar-uang-saku.export', array_filter(['custid' => (int) ($custid ?? 0) ?: null, 'nama' => $nama ?? null])) }}"
                           class="btn btn-success @if((int)($custid ?? 0) <= 0 && trim((string)($nama ?? '')) === '') disabled @endif">
                            <i class="ri ri-file-excel-2-line me-1"></i>Export Excel
                        </a>
                        <small class="text-muted ms-auto">Seq hari ini: <code id="seqBox">{{ $nextSeq ?? '00001' }}</code></small>
                    </div>
                </div>
            </form>

            <form id="formCetak" method="POST" action="{{ route('admin.smartcard.keluar-uang-saku.cetak') }}"
                  target="_blank" class="d-none">
                @csrf
                <input type="hidden" name="custid" id="cetakCustid" value="{{ (int) ($custid ?? 0) }}">
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">
                    <h5 class="mb-0">Siswa</h5>
                    <small class="text-muted" id="siswaCount">{{ count($siswaRows) }} data</small>
                </div>
                <div class="table-responsive" style="max-height:280px;">
                    <table class="table table-sm table-bordered table-hover mb-0">
                        <thead class="table-light sticky-top">
                        <tr>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th class="text-end">SALDO</th>
                            <th>Kelas</th>
                            <th>Kelompok</th>
                            <th>Jenjang</th>
                        </tr>
                        </thead>
                        <tbody id="siswaBody">
                        @forelse ($siswaRows as $row)
                            <tr class="siswa-row" role="button"
                                data-custid="{{ $row->custid }}"
                                data-nis="{{ $row->nis }}"
                                data-nama="{{ $row->nama }}"
                                data-saldo="{{ $row->saldo }}">
                                <td>{{ $row->nis }}</td>
                                <td>{{ $row->nama }}</td>
                                <td class="text-end">{{ number_format($row->saldo, 0, ',', '.') }}</td>
                                <td>{{ $row->kelas }}</td>
                                <td>{{ $row->kelompok }}</td>
                                <td>{{ $row->jenjang }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">Klik CARI atau pilih siswa.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Cash Keluar (CASH)</h5>
                    @if ((int) ($custid ?? 0) > 0)
                        <a href="{{ route('admin.smartcard.keluar-uang-saku.export', ['custid' => (int) $custid, 'nama' => $nama ?? '']) }}"
                           class="btn btn-sm btn-success">
                            <i class="ri ri-file-excel-2-line me-1"></i>Export Excel
                        </a>
                    @endif
                </div>
                <div class="table-responsive" style="max-height:280px;">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light sticky-top">
                        <tr>
                            <th>Tanggal Keluar</th>
                            <th class="text-end">Jumlah</th>
                            <th>User</th>
                        </tr>
                        </thead>
                        <tbody id="cashoutBody">
                        @forelse ($cashoutRows as $row)
                            <tr>
                                <td>{{ $row->tanggal }}</td>
                                <td class="text-end">{{ number_format((int)$row->jumlah, 0, ',', '.') }}</td>
                                <td>{{ $row->teller ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">—</td></tr>
                        @endforelse
                        </tbody>
                        @if (count($cashoutRows) > 0)
                            <tfoot class="table-light">
                            <tr>
                                <th class="text-end">TOTAL</th>
                                <th class="text-end">{{ number_format((int) collect($cashoutRows)->sum('jumlah'), 0, ',', '.') }}</th>
                                <th></th>
                            </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h5 class="mb-0">Riwayat Transaksi</h5>
            <small class="text-muted" id="tranCount">{{ count($tranRows) }} baris</small>
        </div>
        <div class="table-responsive" style="max-height:360px;">
            <table class="table table-sm table-bordered mb-0">
                <thead class="table-light sticky-top">
                <tr>
                    <th>Tanggal</th>
                    <th>METODE</th>
                    <th class="text-end">MASUK</th>
                    <th class="text-end">KELUAR</th>
                    <th>Keterangan</th>
                </tr>
                </thead>
                <tbody id="tranBody">
                @php
                    $sumMasuk = 0; $sumKeluar = 0;
                @endphp
                @forelse ($tranRows as $row)
                    @php
                        $sumMasuk += (int) $row->kredit;
                        $sumKeluar += (int) $row->debet;
                    @endphp
                    <tr>
                        <td>{{ $row->tanggal }}</td>
                        <td>{{ $row->metode }}</td>
                        <td class="text-end">{{ number_format((int)$row->kredit, 0, ',', '.') }}</td>
                        <td class="text-end">{{ number_format((int)$row->debet, 0, ',', '.') }}</td>
                        <td>{{ $row->keterangan }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">—</td></tr>
                @endforelse
                </tbody>
                <tfoot class="table-light">
                <tr>
                    <th colspan="2" class="text-end">Total</th>
                    <th class="text-end" id="totMasuk">{{ number_format($sumMasuk, 0, ',', '.') }}</th>
                    <th class="text-end" id="totKeluar">{{ number_format($sumKeluar, 0, ',', '.') }}</th>
                    <th></th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection

@section('script')
<script>
(function () {
    const searchUrl = @json($searchUrl);
    const detailUrl = @json($detailUrl);

    const nisInput = document.getElementById('nisInput');
    const namaInput = document.getElementById('namaInput');
    const nisList = document.getElementById('nisList');
    const namaList = document.getElementById('namaList');
    const custidEl = document.getElementById('custid');
    const saldoBox = document.getElementById('saldoBox');
    const nominalInput = document.getElementById('nominalInput');
    const btnCash = document.getElementById('btnCashKeluar');
    const btnCari = document.getElementById('btnCari');
    const cetakCustid = document.getElementById('cetakCustid');
    const seqBox = document.getElementById('seqBox');
    const siswaBody = document.getElementById('siswaBody');
    const cashoutBody = document.getElementById('cashoutBody');
    const tranBody = document.getElementById('tranBody');

    let timerNis = null, timerNama = null, seqNis = 0, seqNama = 0;
    let currentSaldo = {{ (int) ($saldo ?? 0) }};

    function fmt(n) {
        return new Intl.NumberFormat('id-ID').format(Number(n || 0));
    }
    function parseAmt(v) {
        return parseInt(String(v || '').replace(/\D/g, ''), 10) || 0;
    }
    function syncBtn() {
        const nom = parseAmt(nominalInput.value);
        const cid = parseInt(custidEl.value || '0', 10);
        btnCash.disabled = !(nom > 0 && cid > 0);
    }
    function closeLists() {
        nisList.style.display = 'none';
        namaList.style.display = 'none';
        nisList.innerHTML = '';
        namaList.innerHTML = '';
    }

    function applySiswa(data) {
        custidEl.value = data.custid || 0;
        cetakCustid.value = data.custid || 0;
        nisInput.value = data.nis || '';
        namaInput.value = data.nama || '';
        currentSaldo = Number(data.saldo || 0);
        saldoBox.value = fmt(currentSaldo);
        if (data.next_seq) seqBox.textContent = data.next_seq;
        syncBtn();
        closeLists();
    }

    function renderSiswaRows(rows) {
        document.getElementById('siswaCount').textContent = (rows || []).length + ' data';
        if (!rows || !rows.length) {
            siswaBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Tidak ada data.</td></tr>';
            return;
        }
        siswaBody.innerHTML = rows.map(function (r) {
            return '<tr class="siswa-row" role="button" data-custid="' + r.custid + '" data-nis="' + (r.nis || '') +
                '" data-nama="' + (r.nama || '').replace(/"/g, '&quot;') + '" data-saldo="' + (r.saldo || 0) + '">' +
                '<td>' + (r.nis || '') + '</td>' +
                '<td>' + (r.nama || '') + '</td>' +
                '<td class="text-end">' + fmt(r.saldo) + '</td>' +
                '<td>' + (r.kelas || '') + '</td>' +
                '<td>' + (r.kelompok || '') + '</td>' +
                '<td>' + (r.jenjang || '') + '</td></tr>';
        }).join('');
        bindSiswaRows();
    }

    function renderCashout(rows) {
        if (!rows || !rows.length) {
            cashoutBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">—</td></tr>';
            return;
        }
        cashoutBody.innerHTML = rows.map(function (r) {
            return '<tr><td>' + (r.tanggal || '-') + '</td>' +
                '<td class="text-end">' + fmt(r.jumlah) + '</td>' +
                '<td>' + (r.teller || '-') + '</td></tr>';
        }).join('');
    }

    function renderTran(rows) {
        document.getElementById('tranCount').textContent = (rows || []).length + ' baris';
        let masuk = 0, keluar = 0;
        if (!rows || !rows.length) {
            tranBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">—</td></tr>';
            document.getElementById('totMasuk').textContent = '0';
            document.getElementById('totKeluar').textContent = '0';
            return;
        }
        tranBody.innerHTML = rows.map(function (r) {
            masuk += Number(r.kredit || 0);
            keluar += Number(r.debet || 0);
            return '<tr><td>' + (r.tanggal || '-') + '</td>' +
                '<td>' + (r.metode || '-') + '</td>' +
                '<td class="text-end">' + fmt(r.kredit) + '</td>' +
                '<td class="text-end">' + fmt(r.debet) + '</td>' +
                '<td>' + (r.keterangan || '-') + '</td></tr>';
        }).join('');
        document.getElementById('totMasuk').textContent = fmt(masuk);
        document.getElementById('totKeluar').textContent = fmt(keluar);
    }

    function loadDetail(custid) {
        if (!custid) return;
        fetch(detailUrl + '?custid=' + encodeURIComponent(custid), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json.ok) return;
                const d = json.data;
                applySiswa(d);
                renderSiswaRows([{
                    custid: d.custid, nis: d.nis, nama: d.nama, saldo: d.saldo,
                    kelas: d.kelas, kelompok: d.kelompok, jenjang: d.jenjang
                }]);
                renderCashout(d.cashout_rows || []);
                renderTran(d.tran_rows || []);
            })
            .catch(function () {});
    }

    function bindSiswaRows() {
        document.querySelectorAll('.siswa-row').forEach(function (tr) {
            tr.addEventListener('click', function () {
                const cid = parseInt(tr.getAttribute('data-custid') || '0', 10);
                loadDetail(cid);
            });
        });
    }

    function searchAuto(q, mode, listEl, seqRef) {
        const mySeq = ++seqRef.n;
        if (q.length < 3) {
            listEl.style.display = 'none';
            listEl.innerHTML = '';
            return;
        }
        fetch(searchUrl + '?q=' + encodeURIComponent(q) + '&mode=' + mode, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (mySeq !== seqRef.n) return;
                const rows = json.rows || [];
                if (!rows.length) {
                    listEl.innerHTML = '<div class="list-group-item text-muted bg-white">Tidak ditemukan</div>';
                    listEl.style.display = 'block';
                    return;
                }
                listEl.innerHTML = rows.map(function (r) {
                    const sub = r.kelas || r.kelompok || '';
                    return '<button type="button" class="list-group-item list-group-item-action bg-white" data-custid="' +
                        r.custid + '"><div class="fw-semibold">' + (r.label || r.nama) + '</div>' +
                        (sub ? '<small class="text-muted">' + sub + '</small>' : '') +
                        '</button>';
                }).join('');
                listEl.style.display = 'block';
                listEl.querySelectorAll('button').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        loadDetail(parseInt(btn.getAttribute('data-custid') || '0', 10));
                    });
                });
            })
            .catch(function () {});
    }

    const refNis = { n: 0 }, refNama = { n: 0 };
    nisInput.addEventListener('input', function () {
        clearTimeout(timerNis);
        timerNis = setTimeout(function () { searchAuto(nisInput.value.trim(), 'nis', nisList, refNis); }, 280);
        syncBtn();
    });
    namaInput.addEventListener('input', function () {
        clearTimeout(timerNama);
        timerNama = setTimeout(function () { searchAuto(namaInput.value.trim(), 'nama', namaList, refNama); }, 280);
        syncBtn();
    });

    nominalInput.addEventListener('input', function () {
        const n = parseAmt(nominalInput.value);
        if (document.activeElement === nominalInput) {
            // keep raw digits while typing; format on blur
        }
        syncBtn();
    });
    nominalInput.addEventListener('blur', function () {
        nominalInput.value = fmt(parseAmt(nominalInput.value));
    });
    nominalInput.addEventListener('focus', function () {
        nominalInput.value = String(parseAmt(nominalInput.value) || '');
    });

    document.getElementById('formKeluar').addEventListener('submit', function (e) {
        const nom = parseAmt(nominalInput.value);
        nominalInput.value = String(nom);
        const cid = parseInt(custidEl.value || '0', 10);
        if (cid <= 0) {
            e.preventDefault();
            alert('NIS harap diisi');
            return;
        }
        if (nom <= 0) {
            e.preventDefault();
            alert('Nominal masih 0');
            return;
        }
        if (nom > currentSaldo) {
            e.preventDefault();
            alert('SALDO Tidak cukup');
            return;
        }
        if (!confirm('Cash keluar Rp ' + fmt(nom) + '?')) {
            e.preventDefault();
            nominalInput.value = fmt(nom);
        }
    });

    btnCari.addEventListener('click', function () {
        const q = (nisInput.value.trim() || namaInput.value.trim());
        const url = new URL(@json(route('admin.smartcard.keluar-uang-saku.index')), window.location.origin);
        url.searchParams.set('search', '1');
        if (q) url.searchParams.set('q', q);
        const cid = parseInt(custidEl.value || '0', 10);
        if (cid > 0) url.searchParams.set('custid', String(cid));
        window.location.href = url.toString();
    });

    document.addEventListener('click', function (e) {
        if (!nisList.contains(e.target) && e.target !== nisInput) nisList.style.display = 'none';
        if (!namaList.contains(e.target) && e.target !== namaInput) namaList.style.display = 'none';
    });

    bindSiswaRows();
    syncBtn();
})();
</script>
@endsection
