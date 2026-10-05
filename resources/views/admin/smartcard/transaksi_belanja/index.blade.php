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
            <form method="GET" action="{{ route('admin.smartcard.transaksi-belanja.index') }}" id="formFilterBelanja">
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
                        <label class="form-label">Nama Kantin</label>
                        <select class="form-select" name="nama_kantin">
                            <option value="">Semua</option>
                            @foreach (($kantinOptions ?? []) as $kantin)
                                <option value="{{ $kantin }}" @selected(($filters['nama_kantin'] ?? '') === $kantin)>{{ $kantin }}</option>
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
                    <div class="col-md-3">
                        <label class="form-label">Tampil data</label>
                        <select class="form-select" name="per_page">
                            @foreach (($perPageOptions ?? [10, 25, 50, 100, 200]) as $opt)
                                <option value="{{ $opt }}" @selected((int) ($perPage ?? 25) === (int) $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 d-flex align-items-end gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">Cari</button>
                        <a href="{{ route('admin.smartcard.transaksi-belanja.export', array_merge(request()->query(), ['search' => 1])) }}"
                           class="btn btn-success">
                            <i class="ri ri-file-excel-2-line me-1"></i>Export Excel
                        </a>
                        <a href="{{ route('admin.smartcard.transaksi-belanja.index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">Data Transaksi Belanja</h5>
            <div class="d-flex align-items-center gap-3">
                @if ($isSearch ?? false)
                    <span class="badge bg-primary fs-6">
                        Total Nominal: Rp {{ number_format((float) ($totalDebet ?? 0), 0, ',', '.') }}
                    </span>
                @endif
                <a href="{{ route('admin.smartcard.transaksi-belanja.export', array_merge(request()->query(), ['search' => 1])) }}"
                   class="btn btn-sm btn-success">
                    <i class="ri ri-file-excel-2-line me-1"></i>Export Excel
                </a>
            </div>
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
                @if (($isSearch ?? false) && method_exists($rows, 'count') && $rows->count() > 0)
                    <tfoot class="table-light">
                    <tr>
                        <th colspan="4" class="text-end">Total Nominal</th>
                        <th class="text-end">{{ number_format((float) ($totalDebet ?? 0), 0, ',', '.') }}</th>
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
