@extends('layouts.admin_new')
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{ $dataTitle ?? 'Setting Blokir Kartu' }}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a>
        </li>
        <li class="breadcrumb-item">smartCARD</li>
        <li class="breadcrumb-item active">Setting Blokir Kartu</li>
    </ul>

    @if (session('smartcard_success'))
        <div class="alert alert-success">{{ session('smartcard_success') }}</div>
    @endif
    @if (session('smartcard_error'))
        <div class="alert alert-danger">{{ session('smartcard_error') }}</div>
    @endif
    @if (($searchError ?? '') !== '')
        <div class="alert alert-danger">{{ $searchError }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Cari Siswa</h5></div>
        <div class="card-body">
            <form id="formSearch" method="GET" action="{{ route('admin.smartcard.setting-blokir-kartu.index') }}">
                <input type="hidden" name="search" value="1">
                <input type="hidden" id="custidHidden" name="custid" value="{{ (int) ($custid ?? 0) }}">

                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label" for="siswaSearchInput">No Induk Siswa</label>
                        <div id="siswaAutoWrap" class="position-relative">
                            <input type="text" class="form-control" id="siswaSearchInput" name="siswa_search"
                                   autocomplete="off" value="{{ $siswaLabel ?? '' }}"
                                   placeholder="Ketik NIS / nama, pilih dari dropdown">
                            <div id="siswaAutoList" class="list-group position-absolute w-100 shadow"
                                 style="display:none; z-index:200; max-height:240px; overflow:auto; top:calc(100% + 4px);"></div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="namaSiswa">Nama</label>
                        <input type="text" class="form-control" id="namaSiswa" value="{{ $nama ?? '' }}"
                               readonly tabindex="-1" placeholder="Otomatis dari NIS">
                    </div>
                    <div class="col-md-2 d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary flex-grow-1">Lihat</button>
                        <a href="{{ route('admin.smartcard.setting-blokir-kartu.index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                    <div class="col-12">
                        <a href="{{ route('admin.smartcard.setting-blokir-kartu.export', request()->query()) }}"
                           class="btn btn-success">
                            <i class="ri ri-file-excel-2-line me-1"></i>Export Excel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">Daftar Kartu</h5>
            <a href="{{ route('admin.smartcard.setting-blokir-kartu.export', request()->query()) }}"
               class="btn btn-sm btn-success">
                <i class="ri ri-file-excel-2-line me-1"></i>Export Excel
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:64px;">No</th>
                        <th>No Kartu</th>
                        <th>PIN</th>
                        <th style="width:140px;">Status</th>
                        <th style="width:180px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (($kartuRows ?? collect()) as $index => $row)
                        @php $isBlocked = (int) ($row->blokir ?? 0) === 1; @endphp
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $row->no_kartu ?? '—' }}</td>
                            <td>{{ $row->pin ?? '—' }}</td>
                            <td>
                                @if ($isBlocked)
                                    <span class="badge bg-danger">Diblokir</span>
                                @else
                                    <span class="badge bg-success">Aktif</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.smartcard.setting-blokir-kartu.update') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="pid" value="{{ $row->no_kartu }}">
                                    <input type="hidden" name="custid" value="{{ (int) ($custid ?? 0) }}">
                                    <input type="hidden" name="siswa_search" value="{{ $siswaLabel ?? '' }}">
                                    @if ($isBlocked)
                                        <input type="hidden" name="blokir" value="0">
                                        <button type="submit" class="btn btn-sm btn-success">Buka Blokir</button>
                                    @else
                                        <input type="hidden" name="blokir" value="1">
                                        <button type="submit" class="btn btn-sm btn-danger"
                                                onclick="return confirm('Blokir kartu ini?')">Blokir</button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                @if ($isSearch ?? false)
                                    @if ((int) ($custid ?? 0) > 0)
                                        Siswa ini belum memiliki kartu.
                                    @else
                                        Pilih siswa dari dropdown lalu klik <strong>Lihat</strong>.
                                    @endif
                                @else
                                    Pilih siswa (NIS) lalu klik <strong>Lihat</strong> untuk menampilkan kartu.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        (function () {
            const siswaSearchUrl = @json(route('admin.smartcard.data-kartu-siswa.siswa-search'));
            const siswaInput = document.getElementById('siswaSearchInput');
            const custidHidden = document.getElementById('custidHidden');
            const namaSiswa = document.getElementById('namaSiswa');
            const siswaList = document.getElementById('siswaAutoList');
            const siswaWrap = document.getElementById('siswaAutoWrap');
            const searchForm = document.getElementById('formSearch');
            let searchTimer = null;
            let searchSeq = 0;

            if (searchForm) {
                searchForm.addEventListener('submit', function (e) {
                    if (!custidHidden || parseInt(custidHidden.value, 10) <= 0) {
                        e.preventDefault();
                        alert('Pilih siswa (NIS) terlebih dahulu dari dropdown.');
                    }
                });
            }

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
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () { fetchSiswa(siswaInput.value); }, 280);
            });

            siswaInput.addEventListener('focus', function () {
                if (String(siswaInput.value || '').trim() !== '') fetchSiswa(siswaInput.value);
            });

            document.addEventListener('click', function (e) {
                if (!siswaWrap.contains(e.target)) closeList();
            });
        })();
    </script>
@endsection
