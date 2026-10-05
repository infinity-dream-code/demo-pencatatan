@extends('layouts.admin_new')
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{ $dataTitle ?? ($mainTitle ?? ($title ?? '')) }}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a>
        </li>
        @isset($title)
            <li class="breadcrumb-item">{{ $title }}</li>
        @endisset
        @isset($mainTitle)
            <li class="breadcrumb-item active">{{ $mainTitle }}</li>
        @endisset
    </ul>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if (!empty($errorMessage))
        <div class="alert alert-danger">{{ $errorMessage }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">Data Pencairan Belanja ke Kantin</h5>
            <a href="{{ route('admin.smartcard.pencairan-kantin.export', request()->query()) }}"
               class="btn btn-sm btn-success">
                <i class="ri ri-file-excel-2-line me-1"></i>Export Excel
            </a>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <form method="GET" action="{{ route('admin.smartcard.pencairan-kantin.index') }}" id="rpFormGet">
                        <input type="hidden" name="nama_penerima" id="namaPenerimaGet" value="{{ $namaPenerima ?? '' }}">
                        <input type="hidden" name="nominal" id="nominalGet" value="{{ $nominal ?? '' }}">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="kdMercan">Merchant</label>
                                <select class="form-select" id="kdMercan" name="kd_mercan">
                                    <option value="">Pilih Merchant</option>
                                    @foreach ($mercanOptions as $m)
                                        <option value="{{ $m->kode }}" @selected(($kdMercan ?? '') === $m->kode)>{{ $m->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="dariTanggal">Dari Tanggal</label>
                                <input type="date" class="form-control" id="dariTanggal" name="dari_tanggal" value="{{ $dariTanggal ?? '' }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="sampaiTanggal">Sampai Tanggal</label>
                                <input type="date" class="form-control" id="sampaiTanggal" name="sampai_tanggal" value="{{ $sampaiTanggal ?? '' }}">
                            </div>
                            <div class="col-12 d-flex gap-2 flex-wrap">
                                <button type="submit" name="cari_transaksi" value="1" class="btn btn-primary">Cari Transaksi</button>
                                <button type="button" id="btnLiatPencairan" class="btn btn-outline-secondary">Liat Pencairan</button>
                                <a href="{{ route('admin.smartcard.pencairan-kantin.export', request()->query()) }}"
                                   class="btn btn-success">
                                    <i class="ri ri-file-excel-2-line me-1"></i>Export Excel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-md-6">
                    <form method="POST" action="{{ route('admin.smartcard.pencairan-kantin.store') }}" id="rpFormSave">
                        @csrf
                        <input type="hidden" name="kd_mercan" id="kdMercanSave" value="{{ $kdMercan ?? '' }}">
                        <input type="hidden" name="dari_tanggal" id="dariTanggalSave" value="{{ $dariTanggal ?? '' }}">
                        <input type="hidden" name="sampai_tanggal" id="sampaiTanggalSave" value="{{ $sampaiTanggal ?? '' }}">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="namaPenerima">Nama Penerima</label>
                                <input type="text" class="form-control" id="namaPenerima" name="nama_penerima"
                                       value="{{ old('nama_penerima', $namaPenerima ?? '') }}" placeholder="Nama penerima">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="nominal">Nominal</label>
                                <input type="number" class="form-control" id="nominal" name="nominal"
                                       value="{{ old('nominal', $nominal !== '' ? $nominal : '') }}" min="1" step="1" placeholder="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">No Terima</label>
                                <input type="text" class="form-control" value="{{ $previewNoTerima ?? '' }}" readonly>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Simpan</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Transaksi Belanja</h5>
                    @if ($cariTransaksi ?? false)
                        <span class="badge bg-primary">Total: Rp {{ number_format((float) ($transaksiTotal ?? 0), 0, ',', '.') }}</span>
                    @endif
                </div>
                <div class="table-responsive" style="max-height: 420px;">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Tgl Transaksi</th>
                            <th class="text-end">Saldo</th>
                            <th>Merchant</th>
                            <th>Kantin</th>
                        </tr>
                        </thead>
                        <tbody>
                        @if ($cariTransaksi ?? false)
                            @forelse (($transaksiRows ?? collect()) as $row)
                                <tr>
                                    <td>
                                        @if (!empty($row->tgl_transaksi) && !str_starts_with((string) $row->tgl_transaksi, '0000-00-00'))
                                            {{ \Illuminate\Support\Carbon::parse($row->tgl_transaksi)->format('d-m-Y H:i') }}
                                        @else — @endif
                                    </td>
                                    <td class="text-end">{{ number_format((float) ($row->saldo ?? 0), 0, ',', '.') }}</td>
                                    <td>{{ $row->mercan ?? '—' }}</td>
                                    <td>{{ $row->kantin ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">Tidak ada transaksi pada periode ini.</td></tr>
                            @endforelse
                            @if (($transaksiRows ?? collect())->isNotEmpty())
                                <tr class="table-light">
                                    <td><strong>Total</strong></td>
                                    <td class="text-end"><strong>{{ number_format((float) ($transaksiTotal ?? 0), 0, ',', '.') }}</strong></td>
                                    <td colspan="2"></td>
                                </tr>
                            @endif
                        @else
                            <tr><td colspan="4" class="text-center text-muted">Pilih merchant & tanggal, lalu klik Cari Transaksi.</td></tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">Data Pencairan</h5>
                    @if ($liatPencairan ?? false)
                        <span class="badge bg-primary">Total Nominal: Rp {{ number_format((float) ($pencairanTotal ?? 0), 0, ',', '.') }}</span>
                    @endif
                </div>
                <div class="table-responsive" style="max-height: 420px;">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Tgl Terima</th>
                            <th>Nama Penerima</th>
                            <th class="text-end">Nominal</th>
                            <th>No Terima</th>
                        </tr>
                        </thead>
                        <tbody>
                        @if ($liatPencairan ?? false)
                            @forelse (($pencairanRows ?? collect()) as $row)
                                <tr>
                                    <td>
                                        @if (!empty($row->tgl_terima) && !str_starts_with((string) $row->tgl_terima, '0000-00-00'))
                                            {{ \Illuminate\Support\Carbon::parse($row->tgl_terima)->format('Y-m-d H:i:s') }}
                                        @else — @endif
                                    </td>
                                    <td>{{ $row->nama_penerima ?? '—' }}</td>
                                    <td class="text-end">{{ number_format((float) ($row->nominal ?? 0), 0, ',', '.') }}</td>
                                    <td>{{ $row->no_terima ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">Belum ada data pencairan.</td></tr>
                            @endforelse
                            @if (($pencairanRows ?? collect())->isNotEmpty())
                                <tr class="table-light">
                                    <td colspan="2"><strong>Total Nominal</strong></td>
                                    <td class="text-end"><strong>{{ number_format((float) ($pencairanTotal ?? 0), 0, ',', '.') }}</strong></td>
                                    <td></td>
                                </tr>
                            @endif
                        @else
                            <tr><td colspan="4" class="text-center text-muted">Klik Liat Pencairan untuk menampilkan data.</td></tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const formGet = document.getElementById('rpFormGet');
            const formSave = document.getElementById('rpFormSave');
            const kdMercan = document.getElementById('kdMercan');
            const dariTanggal = document.getElementById('dariTanggal');
            const sampaiTanggal = document.getElementById('sampaiTanggal');
            const namaPenerima = document.getElementById('namaPenerima');
            const nominal = document.getElementById('nominal');
            const btnLiat = document.getElementById('btnLiatPencairan');

            const syncToSave = () => {
                const kd = document.getElementById('kdMercanSave');
                const dari = document.getElementById('dariTanggalSave');
                const sampai = document.getElementById('sampaiTanggalSave');
                if (kd) kd.value = kdMercan?.value || '';
                if (dari) dari.value = dariTanggal?.value || '';
                if (sampai) sampai.value = sampaiTanggal?.value || '';
            };

            const syncToGetHidden = () => {
                const hNama = document.getElementById('namaPenerimaGet');
                const hNom = document.getElementById('nominalGet');
                if (hNama) hNama.value = namaPenerima?.value || '';
                if (hNom) hNom.value = nominal?.value || '';
            };

            // Sinkronkan setiap perubahan agar Simpan selalu dapat tanggal
            [kdMercan, dariTanggal, sampaiTanggal].forEach(function (el) {
                if (!el) return;
                el.addEventListener('change', syncToSave);
                el.addEventListener('input', syncToSave);
            });
            [namaPenerima, nominal].forEach(function (el) {
                if (!el) return;
                el.addEventListener('change', syncToGetHidden);
                el.addEventListener('input', syncToGetHidden);
            });

            formGet?.addEventListener('submit', () => syncToGetHidden());

            formSave?.addEventListener('submit', function (e) {
                syncToSave();
                if (!kdMercan?.value) {
                    e.preventDefault();
                    alert('Pilih merchant terlebih dahulu.');
                    return;
                }
                if (!dariTanggal?.value) {
                    e.preventDefault();
                    alert('Dari tanggal wajib diisi.');
                    dariTanggal?.focus();
                    return;
                }
                if (!sampaiTanggal?.value) {
                    e.preventDefault();
                    alert('Sampai tanggal wajib diisi.');
                    sampaiTanggal?.focus();
                    return;
                }
                if (!(namaPenerima?.value || '').trim()) {
                    e.preventDefault();
                    alert('Nama penerima wajib diisi.');
                    namaPenerima?.focus();
                    return;
                }
                if (!nominal?.value || parseInt(nominal.value, 10) <= 0) {
                    e.preventDefault();
                    alert('Nominal wajib diisi dan harus lebih dari 0.');
                    nominal?.focus();
                }
            });

            btnLiat?.addEventListener('click', () => {
                syncToGetHidden();
                syncToSave();
                // hapus flag lama jika ada
                formGet.querySelectorAll('input[name="liat_pencairan"], input[name="cari_transaksi"]').forEach(function (el) {
                    if (el.type === 'hidden') el.remove();
                });
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'liat_pencairan';
                input.value = '1';
                formGet.appendChild(input);
                formGet.submit();
            });

            syncToSave();
            syncToGetHidden();
        })();
    </script>
@endsection
