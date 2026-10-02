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

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Filter</h5></div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.smartcard.transaksi-belanja.index') }}">
                <input type="hidden" name="search" value="1">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Tahun Pelajaran</label>
                        <select class="form-select" name="thn_akademik">
                            <option value="">Semua</option>
                            @foreach ($thnAka as $row)
                                @php $val = trim((string) ($row->thn_aka ?? '')); @endphp
                                @if ($val !== '')
                                    <option value="{{ $val }}" @selected(($filters['thn_akademik'] ?? '') === $val)>{{ $val }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
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
                        <input type="text" class="form-control" name="nis" value="{{ $filters['nis'] ?? '' }}" placeholder="NIS">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Nama</label>
                        <input type="text" class="form-control" name="nama" value="{{ $filters['nama'] ?? '' }}" placeholder="Nama siswa">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Dari Tanggal</label>
                        <input type="date" class="form-control" name="dari_tanggal" value="{{ $filters['dari_tanggal'] ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sampai Tanggal</label>
                        <input type="date" class="form-control" name="sampai_tanggal" value="{{ $filters['sampai_tanggal'] ?? '' }}">
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">Cari</button>
                        <a href="{{ route('admin.smartcard.transaksi-belanja.index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Data Transaksi Belanja</h5>
            @if ($isSearch ?? false)
                <small class="text-muted">Total debet: <strong>Rp {{ number_format((float) ($totalDebet ?? 0), 0, ',', '.') }}</strong></small>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>No</th>
                    <th>NIS</th>
                    <th>Nama</th>
                    <th>Tgl Transaksi</th>
                    <th class="text-end">Debet</th>
                    <th>Kantin</th>
                    <th>Kelas</th>
                    <th>Kelompok</th>
                </tr>
                </thead>
                <tbody>
                @forelse (($rows ?? []) as $index => $row)
                    <tr>
                        <td>{{ ($rows->firstItem() ?? 0) + $index }}</td>
                        <td>{{ $row->nis ?? '—' }}</td>
                        <td>{{ $row->nama ?? '—' }}</td>
                        <td>
                            @if (!empty($row->tgl_transaksi))
                                {{ \Illuminate\Support\Carbon::parse($row->tgl_transaksi)->format('d-m-Y H:i') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end">{{ number_format((float) ($row->debet ?? 0), 0, ',', '.') }}</td>
                        <td>{{ $row->kantin ?? '—' }}</td>
                        <td>{{ $row->kelas ?? '—' }}</td>
                        <td>{{ $row->kelompok ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            @if ($isSearch ?? false)
                                Tidak ada transaksi belanja yang sesuai kriteria.
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
