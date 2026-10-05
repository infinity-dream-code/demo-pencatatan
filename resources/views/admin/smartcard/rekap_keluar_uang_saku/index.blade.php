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
        <div class="card-header"><h5 class="mb-0">Filter Rekap Keluar Uang Saku</h5></div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.smartcard.rekap-keluar-uang-saku.index') }}" id="rtFormSearch">
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
                    <div class="col-md-3">
                        <label class="form-label">Tampil data</label>
                        <select class="form-select" name="per_page">
                            @foreach (($perPageOptions ?? [10, 25, 50, 100, 200]) as $opt)
                                <option value="{{ $opt }}" @selected((int) ($perPage ?? 25) === (int) $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">Cari</button>
                        <a href="{{ route('admin.smartcard.rekap-keluar-uang-saku.export', array_merge(request()->query(), ['search' => 1])) }}"
                           class="btn btn-success @if(!($isSearch ?? false)) disabled @endif">
                            <i class="ri ri-file-excel-2-line me-1"></i>Export Excel
                        </a>
                        <a href="{{ route('admin.smartcard.rekap-keluar-uang-saku.index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">Data Rekap Keluar Uang Saku</h5>
            <div class="d-flex align-items-center gap-3">
                @if ($isSearch ?? false)
                    <span class="badge bg-primary fs-6">
                        Total Nominal: Rp {{ number_format((int) ($totals['debet'] ?? 0), 0, ',', '.') }}
                    </span>
                    <small class="text-muted">{{ $rows->total() ?? 0 }} data</small>
                @endif
                <a href="{{ route('admin.smartcard.rekap-keluar-uang-saku.export', array_merge(request()->query(), ['search' => 1])) }}"
                   class="btn btn-sm btn-success @if(!($isSearch ?? false)) disabled @endif">
                    <i class="ri ri-file-excel-2-line me-1"></i>Export Excel
                </a>
            </div>
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
                    <th class="text-end">Keluar</th>
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
                        <td class="text-end">{{ number_format((int) ($row->debet ?? 0), 0, ',', '.') }}</td>
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
                                Tidak ada data rekap keluar uang saku.
                            @else
                                Gunakan filter lalu klik Cari untuk menampilkan data.
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
                @if (($isSearch ?? false) && ($rows->count() ?? 0) > 0)
                    <tfoot class="table-light">
                    <tr>
                        <th colspan="5" class="text-end">TOTAL</th>
                        <th class="text-end">{{ number_format((int) ($totals['debet'] ?? 0), 0, ',', '.') }}</th>
                        <th colspan="3"></th>
                    </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        @if (($isSearch ?? false) && method_exists($rows, 'hasPages') && $rows->hasPages())
            <div class="card-footer">{{ $rows->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>
@endsection
