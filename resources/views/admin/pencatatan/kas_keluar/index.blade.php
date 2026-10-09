@extends('layouts.admin_new')
@section('style')
    <link rel="stylesheet" href="{{asset('main/libs/datatables-bs5/datatables.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/select2/select2.css')}}">
@endsection
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">{{ $dataTitle ?? 'Kas Keluar' }}</h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a></li>
        <li class="breadcrumb-item">{{ $title ?? 'Pencatatan Sederhana' }}</li>
        <li class="breadcrumb-item active">{{ $mainTitle ?? 'Kas Keluar' }}</li>
    </ul>
    <div class="alert alert-info py-2">Kas keluar: kolom <strong>debet</strong> terisi, <strong>kredit</strong> = 0.</div>
    <div class="card">
        <div class="card-header header-elements">
            <h5 class="mb-0 me-2">{{ $dataTitle ?? 'Kas Keluar' }}</h5>
            <div class="card-header-elements ms-auto">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-create">
                    <span class="ri-add-line me-2"></span>Tambah Kas Keluar
                </button>
            </div>
        </div>
        <div class="card-datatable table-responsive text-nowrap">
            <table class="table table-sm table-bordered table-hover" id="main_table"><thead class="table-light"></thead><tbody></tbody></table>
        </div>
    </div>
@endsection
@section('script')
    <script src="{{asset('main/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
    <script src="{{asset('js/datatableCustom/Datatable-0-4.min.js')}}"></script>
    <script src="{{asset('js/helper/errorInputHelper.min.js')}}"></script>
    <script src="{{asset('main/libs/select2/select2.min.js')}}"></script>
    <script>
        const dtOptions = { tableId: 'main_table', formId: false, columnUrl: '{{ $columnsUrl }}', dataUrl: '{{ $datasUrl }}', dataColumns: [], thead: true, paging: true, searching: true, pageLength: 10, order: [[1, 'desc']] };
        document.addEventListener('DOMContentLoaded', function () {
            $('#kode_akun').select2({ dropdownParent: $('#modal-create'), width: '100%' });
            if (dtOptions.dataUrl && dtOptions.columnUrl) getDT(dtOptions);
        });
    </script>
    <form id="addForm" class="mainForm">
        <div class="modal fade" id="modal-create" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Tambah Kas Keluar</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label required">Tanggal</label><input type="datetime-local" class="form-control" name="tanggal" required></div>
                        <div class="mb-3"><label class="form-label required">Akun Kas Keluar</label>
                            <select class="form-select" name="kode_akun" id="kode_akun" required>
                                <option value="">Pilih akun</option>
                                @foreach($akunKeluar as $a)<option value="{{ $a->KodeAkunKeluar }}">{{ $a->KodeAkunKeluar }} - {{ $a->NamaAkunKeluar }}</option>@endforeach
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label required">Nominal</label><input type="number" class="form-control" name="nominal" min="1" required></div>
                        <div class="mb-3"><label class="form-label required">Keterangan</label><input type="text" class="form-control" name="keterangan" maxlength="255" required></div>
                        <div class="mb-3"><label class="form-label">URL Foto Bukti</label><input type="url" class="form-control" name="buktiurl" placeholder="https://..."></div>
                    </div>
                    <div class="modal-footer"><button type="reset" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
                </div>
            </div>
        </div>
    </form>
    <script>
        document.getElementById('addForm').addEventListener('submit', function (e) {
            e.preventDefault();
            loadingAlert();
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const fd = new FormData(this);
            fd.append('_token', csrf);
            fetch('{{ $storeUrl }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf }, body: fd })
                .then(r => r.ok ? r.json() : r.json().then(err => Promise.reject({ status: r.status, error: err })))
                .then(d => { successAlert(d.message); dataReload('main_table'); bootstrap.Modal.getInstance(document.getElementById('modal-create')).hide(); this.reset(); $('#kode_akun').val('').trigger('change'); })
                .catch(err => errorAlert(err.error?.message || 'Gagal menyimpan'));
        });
    </script>
@endsection
