@extends('layouts.admin_new')
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{ $dataTitle ?? 'Data Kartu Siswa' }}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a>
        </li>
        <li class="breadcrumb-item">smartCARD</li>
        <li class="breadcrumb-item active">Data Kartu Siswa</li>
    </ul>

    @if (session('smartcard_success'))
        <div class="alert alert-success">{{ session('smartcard_success') }}</div>
    @endif
    @if (session('smartcard_error'))
        <div class="alert alert-danger">{{ session('smartcard_error') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Form Kartu Siswa</h5></div>
        <div class="card-body">
            <form id="formSearch" method="GET" action="{{ route('admin.smartcard.data-kartu-siswa.index') }}">
                <input type="hidden" name="search" value="1">
                <input type="hidden" id="custidHidden" name="custid" value="{{ (int) ($custid ?? 0) }}">

                <div class="row g-3 align-items-end">
                    <div class="col-md-4 position-relative" id="siswaFieldWrap">
                        <label class="form-label" for="siswaSearchInput">No Induk Siswa</label>
                        <div id="siswaAutoWrap" class="position-relative">
                            <input type="text" class="form-control" id="siswaSearchInput" name="siswa_search"
                                   autocomplete="off" value="{{ $siswaLabel ?? '' }}"
                                   placeholder="Ketik NIS / nama, pilih dari dropdown">
                            <div id="siswaAutoList" class="list-group position-absolute w-100 shadow"
                                 style="display:none; z-index:200; max-height:240px; overflow:auto; top:calc(100% + 4px);"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="noKartuInput">No Kartu</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="noKartuInput" name="no_kartu"
                                   value="{{ $noKartu ?? '' }}" placeholder="isi manual / tapping kartu rfid" autocomplete="off">
                            <button type="button" class="btn btn-outline-warning" id="btnScanBarcode" title="Scan barcode">Scan</button>
                        </div>
                        <small class="text-muted">isi manual / tapping kartu rfid</small>
                        <div id="barcodeScannerWrap" class="border rounded p-2 mt-2 bg-light" hidden>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="fw-semibold">Arahkan kamera ke barcode kartu</small>
                                <button type="button" id="btnCloseBarcode" class="btn btn-sm btn-outline-secondary">Tutup</button>
                            </div>
                            <div id="barcodeReader" style="max-width:360px;margin:0 auto;"></div>
                            <small class="text-muted">Mendukung barcode &amp; QR. Hasil scan otomatis mengisi No Kartu.</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="namaSiswa">Nama</label>
                        <input type="text" class="form-control" id="namaSiswa" value="{{ $nama ?? '' }}"
                               readonly tabindex="-1" placeholder="Otomatis dari NIS">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="pinInput">PIN</label>
                        <input type="text" class="form-control" id="pinInput" name="pin"
                               value="{{ $pin ?? '123' }}" placeholder="123">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="perPageInput">Tampil data</label>
                        <select class="form-select" id="perPageInput" name="per_page">
                            @foreach (($perPageOptions ?? [10, 25, 50, 100, 200]) as $opt)
                                <option value="{{ $opt }}" @selected((int) ($perPage ?? 10) === (int) $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-outline-primary">Lihat</button>
                        <button type="submit" class="btn btn-primary" form="formSave">Simpan</button>
                        <a href="{{ route('admin.smartcard.data-kartu-siswa.export', request()->query()) }}"
                           class="btn btn-success">
                            <i class="ri ri-file-excel-2-line me-1"></i>Export Excel
                        </a>
                        <a href="{{ route('admin.smartcard.data-kartu-siswa.index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>

            <form id="formSave" method="POST" action="{{ route('admin.smartcard.data-kartu-siswa.store') }}">
                @csrf
                <input type="hidden" name="custid" id="custidSave" value="{{ (int) ($custid ?? 0) }}">
                <input type="hidden" name="no_kartu" id="noKartuSave" value="{{ $noKartu ?? '' }}">
                <input type="hidden" name="pin" id="pinSave" value="{{ $pin ?? '123' }}">
            </form>

            <form id="formUpdatePin" method="POST" action="{{ route('admin.smartcard.data-kartu-siswa.update-pin') }}" class="d-none">
                @csrf
                <input type="hidden" name="no_kartu" id="editNoKartu" value="">
                <input type="hidden" name="pin" id="editPinHidden" value="">
                <input type="hidden" name="per_page" value="{{ (int) ($perPage ?? 10) }}">
            </form>
        </div>
    </div>

    <div class="modal fade" id="modalEditPin" tabindex="-1" aria-labelledby="modalEditPinLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditPinLabel">Edit PIN Kartu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2"><strong id="editPinNama">—</strong></p>
                    <p class="text-muted small mb-3">No Kartu: <code id="editPinNoKartuLabel">—</code></p>
                    <label class="form-label" for="editPinInput">PIN baru</label>
                    <input type="text" class="form-control" id="editPinInput" maxlength="20" placeholder="123">
                    <small class="text-muted">Hanya PIN yang bisa diubah.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="btnSaveEditPin">Simpan PIN</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">Daftar Kartu Siswa</h5>
            <div class="d-flex align-items-center gap-2">
                @if (isset($kartuRows) && method_exists($kartuRows, 'total'))
                    <small class="text-muted">{{ number_format($kartuRows->total() ?? 0, 0, ',', '.') }} data</small>
                @endif
                <a href="{{ route('admin.smartcard.data-kartu-siswa.export', request()->query()) }}"
                   class="btn btn-sm btn-success">
                    <i class="ri ri-file-excel-2-line me-1"></i>Export Excel
                </a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:64px;">No</th>
                        <th>NIS</th>
                        <th>Nama</th>
                        <th>No Kartu</th>
                        <th>PIN</th>
                        <th style="width:100px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (($kartuRows ?? null) as $index => $row)
                        <tr>
                            <td>{{ ($kartuRows->firstItem() ?? 0) + $index }}</td>
                            <td>{{ $row->nis ?? '—' }}</td>
                            <td>{{ $row->nama ?? '—' }}</td>
                            <td>{{ $row->no_kartu ?? '—' }}</td>
                            <td>{{ $row->pin ?? '—' }}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary btn-edit-pin"
                                        data-no-kartu="{{ $row->no_kartu ?? '' }}"
                                        data-pin="{{ $row->pin ?? '123' }}"
                                        data-nama="{{ $row->nama ?? '' }}">
                                    Edit PIN
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Data kartu tidak ditemukan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (isset($kartuRows) && method_exists($kartuRows, 'hasPages') && $kartuRows->hasPages())
            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                <small class="text-muted">
                    Menampilkan {{ $kartuRows->firstItem() ?? 0 }} sampai {{ $kartuRows->lastItem() ?? 0 }}
                    dari {{ number_format($kartuRows->total() ?? 0, 0, ',', '.') }} entri
                </small>
                <div>
                    {{ $kartuRows->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @endif
    </div>

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        (function () {
            const siswaSearchUrl = @json(route('admin.smartcard.data-kartu-siswa.siswa-search'));
            const siswaInput = document.getElementById('siswaSearchInput');
            const custidHidden = document.getElementById('custidHidden');
            const custidSave = document.getElementById('custidSave');
            const namaSiswa = document.getElementById('namaSiswa');
            const noKartuInput = document.getElementById('noKartuInput');
            const noKartuSave = document.getElementById('noKartuSave');
            const pinInput = document.getElementById('pinInput');
            const pinSave = document.getElementById('pinSave');
            const siswaList = document.getElementById('siswaAutoList');
            const siswaWrap = document.getElementById('siswaAutoWrap');
            const btnScanBarcode = document.getElementById('btnScanBarcode');
            const btnCloseBarcode = document.getElementById('btnCloseBarcode');
            const barcodeScannerWrap = document.getElementById('barcodeScannerWrap');
            let searchTimer = null;
            let searchSeq = 0;
            let barcodeScanner = null;
            let barcodeScannerActive = false;

            const parseBarcodeContent = function (raw) {
                const value = String(raw || '').trim();
                if (!value) return '';
                if (value.startsWith('{') || value.startsWith('[')) {
                    try {
                        const json = JSON.parse(value);
                        for (const key of ['no_kartu', 'pid', 'PID', 'tap_id', 'TAP_ID', 'id', 'card_id']) {
                            if (json[key]) return String(json[key]).trim();
                        }
                    } catch (e) {}
                }
                const urlMatch = value.match(/[?&](?:no_kartu|pid|tap_id|id)=([^&]+)/i);
                if (urlMatch) return decodeURIComponent(urlMatch[1]).trim();
                if (value.includes('|')) return value.split('|')[0].trim();
                return value;
            };

            const syncSaveFields = function () {
                if (custidSave) custidSave.value = custidHidden ? custidHidden.value : '';
                if (noKartuSave && noKartuInput) noKartuSave.value = noKartuInput.value;
                if (pinSave && pinInput) pinSave.value = pinInput.value || '123';
            };

            const setNoKartuFromScan = function (raw) {
                const code = parseBarcodeContent(raw);
                if (!code || !noKartuInput) return '';
                noKartuInput.value = code;
                syncSaveFields();
                noKartuInput.focus();
                noKartuInput.select();
                return code;
            };

            const stopBarcodeScanner = function () {
                if (barcodeScanner && barcodeScannerActive) {
                    barcodeScanner.stop().then(function () {
                        barcodeScanner.clear();
                    }).catch(function () {});
                    barcodeScannerActive = false;
                }
                if (barcodeScannerWrap) barcodeScannerWrap.hidden = true;
            };

            const startBarcodeScanner = async function () {
                if (!barcodeScannerWrap || typeof Html5Qrcode === 'undefined') {
                    alert('Library scanner belum siap. Muat ulang halaman lalu coba lagi.');
                    return;
                }
                if (barcodeScannerActive) {
                    stopBarcodeScanner();
                    return;
                }
                barcodeScannerWrap.hidden = false;
                if (!barcodeScanner) {
                    barcodeScanner = new Html5Qrcode('barcodeReader');
                }
                try {
                    await barcodeScanner.start(
                        { facingMode: 'environment' },
                        { fps: 10, qrbox: { width: 280, height: 140 } },
                        function (decodedText) {
                            const code = setNoKartuFromScan(decodedText);
                            if (code) stopBarcodeScanner();
                        },
                        function () {}
                    );
                    barcodeScannerActive = true;
                } catch (err) {
                    stopBarcodeScanner();
                    alert('Tidak dapat membuka kamera. Pastikan izin kamera aktif, atau isi No Kartu manual / gunakan scanner USB.');
                }
            };

            if (btnScanBarcode) btnScanBarcode.addEventListener('click', startBarcodeScanner);
            if (btnCloseBarcode) btnCloseBarcode.addEventListener('click', stopBarcodeScanner);
            if (noKartuInput) {
                noKartuInput.addEventListener('input', syncSaveFields);
                noKartuInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        setNoKartuFromScan(noKartuInput.value);
                    }
                });
            }
            if (pinInput) pinInput.addEventListener('input', syncSaveFields);

            document.getElementById('formSave')?.addEventListener('submit', function (e) {
                syncSaveFields();
                if (!custidSave || parseInt(custidSave.value, 10) <= 0) {
                    e.preventDefault();
                    alert('Pilih siswa (NIS) terlebih dahulu dari dropdown.');
                    return;
                }
                if (!noKartuSave || noKartuSave.value.trim() === '') {
                    e.preventDefault();
                    alert('Nomor kartu wajib diisi.');
                }
            });

            if (!siswaInput || !custidHidden || !siswaList || !siswaWrap) return;

            const closeList = function () {
                siswaList.style.display = 'none';
                siswaList.innerHTML = '';
            };

            const renderRows = function (matched) {
                if (!matched.length) {
                    siswaList.innerHTML = '<div class="list-group-item text-muted small">Siswa tidak ditemukan.</div>';
                    siswaList.style.display = 'block';
                    return;
                }
                siswaList.innerHTML = matched.map(function (r) {
                    const label = (r.label || '').replace(/"/g, '&quot;');
                    const nmcust = (r.nmcust || '').replace(/"/g, '&quot;');
                    return '<button type="button" class="list-group-item list-group-item-action" data-cid="' + r.cid +
                        '" data-label="' + label + '" data-nmcust="' + nmcust + '">' + (r.label || '—') + '</button>';
                }).join('');
                siswaList.style.display = 'block';
                Array.from(siswaList.querySelectorAll('button[data-cid]')).forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        siswaInput.value = btn.getAttribute('data-label') || '';
                        custidHidden.value = btn.getAttribute('data-cid') || '';
                        if (namaSiswa) namaSiswa.value = btn.getAttribute('data-nmcust') || '';
                        syncSaveFields();
                        closeList();
                    });
                });
            };

            const fetchSiswa = function (q) {
                const query = String(q || '').trim();
                if (query.length < 1) {
                    closeList();
                    return;
                }
                const seq = ++searchSeq;
                siswaList.innerHTML = '<div class="list-group-item text-muted small">Mencari…</div>';
                siswaList.style.display = 'block';
                fetch(siswaSearchUrl + '?q=' + encodeURIComponent(query), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function (res) { return res.json(); })
                    .then(function (json) {
                        if (seq !== searchSeq) return;
                        renderRows(Array.isArray(json.rows) ? json.rows : []);
                    })
                    .catch(function () {
                        if (seq !== searchSeq) return;
                        siswaList.innerHTML = '<div class="list-group-item text-danger small">Gagal memuat data siswa.</div>';
                        siswaList.style.display = 'block';
                    });
            };

            siswaInput.addEventListener('input', function () {
                custidHidden.value = '';
                if (namaSiswa) namaSiswa.value = '';
                syncSaveFields();
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () { fetchSiswa(siswaInput.value); }, 280);
            });

            siswaInput.addEventListener('focus', function () {
                if (String(siswaInput.value || '').trim() !== '') fetchSiswa(siswaInput.value);
            });

            document.addEventListener('click', function (e) {
                if (!siswaWrap.contains(e.target)) closeList();
            });

            window.addEventListener('beforeunload', stopBarcodeScanner);
            syncSaveFields();

            document.querySelectorAll('.btn-edit-pin').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const noKartu = btn.getAttribute('data-no-kartu') || '';
                    const pin = btn.getAttribute('data-pin') || '123';
                    const nama = btn.getAttribute('data-nama') || '—';
                    document.getElementById('editNoKartu').value = noKartu;
                    document.getElementById('editPinInput').value = pin;
                    document.getElementById('editPinNama').textContent = nama;
                    document.getElementById('editPinNoKartuLabel').textContent = noKartu || '—';
                    const modalEl = document.getElementById('modalEditPin');
                    if (window.bootstrap && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    } else {
                        modalEl.style.display = 'block';
                        modalEl.classList.add('show');
                    }
                });
            });

            document.getElementById('btnSaveEditPin')?.addEventListener('click', function () {
                const pin = String(document.getElementById('editPinInput').value || '').trim();
                if (!pin) {
                    alert('PIN wajib diisi.');
                    return;
                }
                document.getElementById('editPinHidden').value = pin;
                document.getElementById('formUpdatePin').submit();
            });
        })();
    </script>
@endsection
