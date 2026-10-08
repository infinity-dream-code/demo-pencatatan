@extends('layouts.admin_new')
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{ $dataTitle ?? 'TOPUP CASH SALDO' }}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a>
        </li>
        <li class="breadcrumb-item">smartCARD</li>
        <li class="breadcrumb-item active">TOP UP Saldo</li>
    </ul>

    @if (session('smartcard_success'))
        <div class="alert alert-success">{{ session('smartcard_success') }}</div>
    @endif
    @if (session('smartcard_error'))
        <div class="alert alert-danger">{{ session('smartcard_error') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header bg-primary">
            <h5 class="mb-0 text-white">TOPUP CASH SALDO</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.smartcard.topup-saldo.store') }}" id="formTopup">
                @csrf
                <input type="hidden" name="custid" id="custid" value="{{ (int) ($custid ?? 0) }}">

                <div class="row g-3 align-items-end">
                    <div class="col-md-3 position-relative">
                        <label class="form-label">NIS</label>
                        <input type="text" class="form-control" id="nisInput" name="nis_display"
                               value="{{ $nis ?? '' }}" autocomplete="off"
                               placeholder="Ketik min. 3 karakter">
                        <div id="nisList" class="list-group position-absolute w-100 shadow border bg-white"
                             style="display:none;z-index:1050;max-height:240px;overflow:auto;top:calc(100% + 2px);background:#fff !important;"></div>
                    </div>
                    <div class="col-md-3 position-relative">
                        <label class="form-label">NAMA</label>
                        <input type="text" class="form-control" id="namaInput" name="nama_display"
                               value="{{ $nama ?? '' }}" autocomplete="off"
                               placeholder="Ketik min. 3 karakter">
                        <div id="namaList" class="list-group position-absolute w-100 shadow border bg-white"
                             style="display:none;z-index:1050;max-height:240px;overflow:auto;top:calc(100% + 2px);background:#fff !important;"></div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Metode</label>
                        <select class="form-select" name="metode" id="metodeInput">
                            @foreach ($methods as $m)
                                <option value="{{ $m }}" @selected(($metode ?? 'CASH') === $m)>{{ $m === 'CASH' ? 'Cash' : $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">TOP UP</label>
                        <input type="text" class="form-control fw-bold" name="nominal" id="nominalInput"
                               inputmode="numeric" value="0" autocomplete="off">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Keterangan</label>
                        <input type="text" class="form-control" name="keterangan" id="ketInput"
                               value="{{ $keterangan ?? '' }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">SALDO</label>
                        <input type="text" class="form-control fw-bold text-primary" id="saldoBox"
                               value="{{ number_format((int) ($saldo ?? 0), 0, ',', '.') }}" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tanggal Manual</label>
                        <input type="date" class="form-control" name="tanggal_manual" id="tglInput"
                               value="{{ ($tanggalManual ?? '') !== '0000-00-00' ? ($tanggalManual ?? '') : '' }}">
                    </div>
                    <div class="col-12 d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-primary" id="btnCari">Cari</button>
                        <button type="submit" class="btn btn-primary" id="btnTopup" disabled>TOPUP</button>
                        <button type="submit" class="btn btn-outline-secondary" form="formCetak"
                                @disabled((int)($custid ?? 0) <= 0)>Cetak Kuitansi</button>
                        <a href="{{ $exportUrl ?? route('admin.smartcard.topup-saldo.export', array_filter(['custid' => (int) ($custid ?? 0) ?: null, 'nama' => $nama ?? null])) }}"
                           class="btn btn-success @if((int)($custid ?? 0) <= 0 && trim((string)($nama ?? '')) === '') disabled @endif">
                            <i class="ri ri-file-excel-2-line me-1"></i>Export Excel
                        </a>
                        <a href="{{ route('admin.smartcard.topup-saldo.index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>

            <form id="formCetak" method="POST" action="{{ route('admin.smartcard.topup-saldo.cetak') }}" target="_blank" class="d-none">
                @csrf
                <input type="hidden" name="custid" id="cetakCustid" value="{{ (int) ($custid ?? 0) }}">
                <input type="hidden" name="transno" id="cetakTransno" value="{{ $lastTransNo ?? '' }}">
                <input type="hidden" name="nominal" id="cetakNominal" value="">
            </form>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <h5 class="mb-0">Siswa</h5>
                    <small class="text-muted" id="siswaCount">{{ count($siswaRows) }} data</small>
                </div>
                <div class="table-responsive" style="max-height:420px;">
                    <table class="table table-sm table-bordered table-hover mb-0">
                        <thead class="table-light sticky-top">
                        <tr>
                            <th>NIS</th>
                            <th>Nama</th>
                            <th class="text-end">Saldo</th>
                            <th>Kelas</th>
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
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">Klik Cari atau pilih siswa dari dropdown.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Riwayat TOPUP</h5>
                    @if ((int) ($custid ?? 0) > 0)
                        <a href="{{ route('admin.smartcard.topup-saldo.export', ['custid' => (int) $custid, 'nama' => $nama ?? '']) }}"
                           class="btn btn-sm btn-success">
                            <i class="ri ri-file-excel-2-line me-1"></i>Export Excel
                        </a>
                    @endif
                </div>
                <div class="table-responsive" style="max-height:200px;">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>No Transaksi</th>
                            <th>Tgl Transaksi</th>
                            <th class="text-end">TOPUP</th>
                            <th>User</th>
                        </tr>
                        </thead>
                        <tbody id="topupBody">
                        @forelse ($topupRows as $row)
                            <tr>
                                <td>{{ $row->no_transaksi }}</td>
                                <td>
                                    @if (!empty($row->tgl_transaksi))
                                        {{ \Illuminate\Support\Carbon::parse($row->tgl_transaksi)->format('Y-m-d H:i:s') }}
                                    @else — @endif
                                </td>
                                <td class="text-end">{{ number_format((int)$row->topup, 0, ',', '.') }}</td>
                                <td>{{ $row->user ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">—</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Transaksi</h5></div>
                <div class="table-responsive" style="max-height:200px;">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Metode</th>
                            <th class="text-end">Kredit</th>
                            <th class="text-end">Debet</th>
                            <th>Keterangan</th>
                        </tr>
                        </thead>
                        <tbody id="tranBody">
                        @forelse ($tranRows as $row)
                            <tr>
                                <td>
                                    @if (!empty($row->tanggal))
                                        {{ \Illuminate\Support\Carbon::parse($row->tanggal)->format('Y-m-d H:i:s') }}
                                    @else — @endif
                                </td>
                                <td>{{ $row->metode }}</td>
                                <td class="text-end">{{ number_format((int)$row->kredit, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format((int)$row->debet, 0, ',', '.') }}</td>
                                <td>{{ $row->keterangan ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">—</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
(function () {
    const searchUrl = @json($searchUrl);
    const detailUrl = @json($detailUrl);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

    const nisInput = document.getElementById('nisInput');
    const namaInput = document.getElementById('namaInput');
    const nisList = document.getElementById('nisList');
    const namaList = document.getElementById('namaList');
    const custidEl = document.getElementById('custid');
    const saldoBox = document.getElementById('saldoBox');
    const nominalInput = document.getElementById('nominalInput');
    const btnTopup = document.getElementById('btnTopup');
    const btnCari = document.getElementById('btnCari');

    let timerNis = null, timerNama = null, seqNis = 0, seqNama = 0;

    function fmt(n) {
        return new Intl.NumberFormat('id-ID').format(Number(n || 0));
    }
    function parseAmt(v) {
        return parseInt(String(v || '').replace(/\D/g, ''), 10) || 0;
    }
    function syncTopupBtn() {
        const nom = parseAmt(nominalInput.value);
        const cid = parseInt(custidEl.value || '0', 10);
        btnTopup.disabled = !(nom > 0 && cid > 0);
    }

    function closeLists() {
        nisList.style.display = 'none';
        namaList.style.display = 'none';
        nisList.innerHTML = '';
        namaList.innerHTML = '';
    }

    function selectSiswa(row) {
        custidEl.value = row.custid || '';
        nisInput.value = row.nis || '';
        namaInput.value = row.nama || '';
        saldoBox.value = '…';
        document.getElementById('cetakCustid').value = row.custid || '';
        closeLists();
        syncTopupBtn();
        loadDetail(row.custid);
    }

    function renderList(el, rows, seqCheck, seq) {
        if (seqCheck !== seq) return;
        if (!rows.length) {
            el.innerHTML = '<div class="list-group-item text-muted small bg-white">Tidak ditemukan</div>';
            el.style.display = 'block';
            return;
        }
        el.innerHTML = rows.map(function (r) {
            const sub = r.kelas || r.kelompok || '';
            return '<button type="button" class="list-group-item list-group-item-action py-2 bg-white" data-json="' +
                encodeURIComponent(JSON.stringify(r)) + '">' +
                '<div class="fw-semibold">' + (r.label || '') + '</div>' +
                (sub ? '<small class="text-muted">' + sub + '</small>' : '') +
                '</button>';
        }).join('');
        el.style.display = 'block';
        Array.from(el.querySelectorAll('button[data-json]')).forEach(function (btn) {
            btn.addEventListener('click', function () {
                selectSiswa(JSON.parse(decodeURIComponent(btn.getAttribute('data-json'))));
            });
        });
    }

    function fetchSearch(q, mode, listEl, seqRef, setSeq) {
        const query = String(q || '').trim();
        if (query.length < 3) {
            listEl.style.display = 'none';
            listEl.innerHTML = '';
            return;
        }
        const seq = setSeq();
        listEl.innerHTML = '<div class="list-group-item text-muted small bg-white">Mencari…</div>';
        listEl.style.display = 'block';
        fetch(searchUrl + '?q=' + encodeURIComponent(query) + '&mode=' + mode, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                renderList(listEl, Array.isArray(json.rows) ? json.rows : [], seqRef(), seq);
            })
            .catch(function () {
                if (seqRef() !== seq) return;
                listEl.innerHTML = '<div class="list-group-item text-danger small bg-white">Gagal mencari</div>';
            });
    }

    async function loadDetail(custid) {
        if (!custid) return;
        try {
            const res = await fetch(detailUrl + '?custid=' + custid, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const json = await res.json();
            if (!json.ok) return;
            const d = json.data;
            saldoBox.value = fmt(d.saldo);
            const topupBody = document.getElementById('topupBody');
            const tranBody = document.getElementById('tranBody');
            if (!(d.topup_rows || []).length) {
                topupBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">—</td></tr>';
            } else {
                topupBody.innerHTML = d.topup_rows.map(function (r) {
                    return '<tr><td>' + (r.no_transaksi || '') + '</td><td>' + (r.tgl_transaksi || '') +
                        '</td><td class="text-end">' + fmt(r.topup) + '</td><td>' + (r.user || '-') + '</td></tr>';
                }).join('');
                if (d.topup_rows[0] && d.topup_rows[0].no_transaksi) {
                    document.getElementById('cetakTransno').value = d.topup_rows[0].no_transaksi;
                }
            }
            if (!(d.tran_rows || []).length) {
                tranBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">—</td></tr>';
            } else {
                tranBody.innerHTML = d.tran_rows.map(function (r) {
                    return '<tr><td>' + (r.tanggal || '') + '</td><td>' + (r.metode || '') +
                        '</td><td class="text-end">' + fmt(r.kredit) + '</td><td class="text-end">' + fmt(r.debet) +
                        '</td><td>' + (r.keterangan || '-') + '</td></tr>';
                }).join('');
            }
        } catch (e) {}
    }

    nisInput.addEventListener('input', function () {
        custidEl.value = '';
        syncTopupBtn();
        clearTimeout(timerNis);
        timerNis = setTimeout(function () {
            fetchSearch(nisInput.value, 'nis', nisList, function () { return seqNis; }, function () { return ++seqNis; });
        }, 400);
    });

    namaInput.addEventListener('input', function () {
        custidEl.value = '';
        syncTopupBtn();
        clearTimeout(timerNama);
        timerNama = setTimeout(function () {
            fetchSearch(namaInput.value, 'nama', namaList, function () { return seqNama; }, function () { return ++seqNama; });
        }, 400);
    });

    document.addEventListener('click', function (e) {
        if (!nisInput.contains(e.target) && !nisList.contains(e.target)) nisList.style.display = 'none';
        if (!namaInput.contains(e.target) && !namaList.contains(e.target)) namaList.style.display = 'none';
    });

    nominalInput.addEventListener('input', function () {
        const n = parseAmt(nominalInput.value);
        if (nominalInput.value !== '' && String(n) !== String(nominalInput.value).replace(/\D/g, '')) {
            nominalInput.value = n ? String(n) : '';
        }
        document.getElementById('cetakNominal').value = n || '';
        syncTopupBtn();
    });

    document.getElementById('formTopup').addEventListener('submit', function (e) {
        const nom = parseAmt(nominalInput.value);
        const cid = parseInt(custidEl.value || '0', 10);
        if (!cid || nom <= 0) {
            e.preventDefault();
            alert('Nominal masih 0, atau NIS dan nama belum diisi');
            return;
        }
        // kirim angka bersih
        nominalInput.value = nom;
    });

    btnCari.addEventListener('click', function () {
        const q = (nisInput.value || namaInput.value || '').trim();
        const url = new URL(@json(route('admin.smartcard.topup-saldo.index')), window.location.origin);
        url.searchParams.set('search', '1');
        if (q) url.searchParams.set('q', q);
        if (custidEl.value) url.searchParams.set('custid', custidEl.value);
        window.location.href = url.toString();
    });

    document.querySelectorAll('.siswa-row').forEach(function (tr) {
        tr.addEventListener('click', function () {
            selectSiswa({
                custid: tr.dataset.custid,
                nis: tr.dataset.nis,
                nama: tr.dataset.nama,
                saldo: tr.dataset.saldo,
            });
            document.querySelectorAll('.siswa-row').forEach(r => r.classList.remove('table-primary'));
            tr.classList.add('table-primary');
        });
    });

    syncTopupBtn();
    @if ((int)($custid ?? 0) > 0)
    loadDetail({{ (int) $custid }});
    @endif
})();
</script>
@endsection
