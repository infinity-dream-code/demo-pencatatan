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

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Filter Rekap TOPUP</h5></div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.smartcard.rekap-topup.index') }}" id="rtFormSearch">
                <input type="hidden" name="search" value="1">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Tahun Angkatan</label>
                        <select class="form-select" name="thn_angkatan">
                            <option value="">Semua</option>
                            @foreach ($thnAka as $row)
                                @php $val = trim((string) ($row->thn_aka ?? '')); @endphp
                                @if ($val !== '')
                                    <option value="{{ $val }}" @selected(($filters['thn_angkatan'] ?? '') === $val)>{{ $val }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Kelas</label>
                        <select class="form-select" name="kelas_id">
                            <option value="">Semua</option>
                            @foreach ($kelasOptions as $k)
                                @php
                                    $id = (string) ($k->id ?? '');
                                    $parts = array_values(array_filter([
                                        trim((string) ($k->unit ?? '')),
                                        trim((string) ($k->jenjang ?? '')),
                                        trim((string) ($k->kelas ?? '')),
                                    ], static fn ($v) => $v !== ''));
                                    $lbl = implode(' - ', $parts);
                                @endphp
                                @if ($id !== '' && $lbl !== '')
                                    <option value="{{ $id }}" @selected(($filters['kelas_id'] ?? '') === $id)>{{ $lbl }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">NIS</label>
                        <input type="text" class="form-control" name="nis" value="{{ $filters['nis'] ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Nama</label>
                        <input type="text" class="form-control" name="nama" value="{{ $filters['nama'] ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Dari Tanggal</label>
                        <input type="date" class="form-control" name="dari_tanggal"
                               value="{{ ($filters['dari_tanggal'] ?? '') !== '0000-00-00' ? ($filters['dari_tanggal'] ?? '') : '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sampai Tanggal</label>
                        <input type="date" class="form-control" name="sampai_tanggal"
                               value="{{ ($filters['sampai_tanggal'] ?? '') !== '0000-00-00' ? ($filters['sampai_tanggal'] ?? '') : '' }}">
                    </div>
                    <div class="col-md-6 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">Cari</button>
                        <button type="submit" form="rtFormCetak" class="btn btn-outline-primary" @disabled(!($isSearch ?? false))>
                            Cetak Rekap
                        </button>
                        <a href="{{ route('admin.smartcard.rekap-topup.index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.smartcard.rekap-topup.cetak') }}" id="rtFormCetak" target="_blank" class="d-none">
                @csrf
                <input type="hidden" name="thn_angkatan" value="{{ $filters['thn_angkatan'] ?? '' }}">
                <input type="hidden" name="kelas_id" value="{{ $filters['kelas_id'] ?? '' }}">
                <input type="hidden" name="nis" value="{{ $filters['nis'] ?? '' }}">
                <input type="hidden" name="nama" value="{{ $filters['nama'] ?? '' }}">
                <input type="hidden" name="dari_tanggal" value="{{ $filters['dari_tanggal'] ?? '' }}">
                <input type="hidden" name="sampai_tanggal" value="{{ $filters['sampai_tanggal'] ?? '' }}">
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Data Rekap TOPUP</h5>
            @if ($isSearch ?? false)
                <small class="text-muted">{{ $rows->total() ?? 0 }} data</small>
            @endif
        </div>
        @if (!empty($errorMessage))
            <div class="alert alert-danger m-3 mb-0">{{ $errorMessage }}</div>
        @endif
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>Kelas</th>
                    <th>Gender</th>
                    <th>Lokasi</th>
                    <th>NIS</th>
                    <th>Nama</th>
                    <th class="text-end">TOPUP</th>
                    <th>Tgl Transaksi</th>
                    <th>No Transaksi</th>
                    <th>User</th>
                </tr>
                </thead>
                <tbody>
                @forelse (($rows ?? []) as $row)
                    <tr>
                        <td>{{ $row->kelas ?? '—' }}</td>
                        <td>{{ $row->gender ?? '—' }}</td>
                        <td>{{ $row->lokasi ?? '—' }}</td>
                        <td>{{ $row->nis ?? '—' }}</td>
                        <td>{{ $row->nama ?? '—' }}</td>
                        <td class="text-end">{{ number_format((int) ($row->topup ?? 0), 0, ',', '.') }}</td>
                        <td>
                            @if (!empty($row->tgl_transaksi))
                                {{ \Illuminate\Support\Carbon::parse($row->tgl_transaksi)->format('Y-m-d H:i:s') }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $row->no_transaksi ?? '—' }}</td>
                        <td>{{ $row->user ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            @if ($isSearch ?? false)
                                Tidak ada data rekap top up.
                            @else
                                Gunakan filter lalu klik Cari untuk menampilkan data.
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if (($isSearch ?? false) && method_exists($rows, 'hasPages') && $rows->hasPages())
            <div class="card-footer">{{ $rows->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>
@endsection
