@extends('layouts.admin_new')
@section('style')
    <link rel="stylesheet" href="{{asset('main/libs/datatables-bs5/datatables.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
@endsection
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{ $dataTitle ?? 'Akun Kas Keluar' }}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a>
        </li>
        <li class="breadcrumb-item">{{ $title ?? 'Pencatatan Sederhana' }}</li>
        <li class="breadcrumb-item active">{{ $mainTitle ?? 'Akun Kas Keluar' }}</li>
    </ul>

    <div class="card">
        <div class="card-header header-elements">
            <h5 class="mb-0 me-2">{{ $dataTitle ?? 'Akun Kas Keluar' }}</h5>
            <div class="card-header-elements ms-auto">
                <div class="d-flex justify-content-center justify-content-md-end gap-2">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                            data-bs-target="#modal-create" title="Tambah Data">
                        <span class="ri-add-line me-2"></span>
                        Tambah
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btn-refresh" title="Refresh">
                        <span class="ri-refresh-line me-2"></span>
                        Refresh
                    </button>
                </div>
            </div>
        </div>
        <div class="card-datatable table-responsive text-nowrap">
            <table class="table table-sm table-bordered table-hover" id="main_table">
                <thead class="table-light"></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
@endsection

@section('script')
    <script src="{{asset('main/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
    <script src="{{asset('js/datatableCustom/Datatable-0-4.min.js')}}"></script>
    <script src="{{asset('js/helper/errorInputHelper.min.js')}}"></script>

    <script type="text/javascript">
        const dtOptions = {
            tableId: 'main_table',
            formId: false,
            columnUrl: '{{ $columnsUrl ?? null }}',
            dataUrl: '{{ $datasUrl ?? null }}',
            dataColumns: [],
            thead: true,
            tfoot: true,
            paging: true,
            searching: true,
            fixedHeader: false,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 75, 100],
        };

        document.addEventListener("DOMContentLoaded", function () {
            if (dtOptions.dataUrl && dtOptions.columnUrl) {
                getDT(dtOptions);
            }

            document.getElementById('btn-refresh')?.addEventListener('click', function () {
                dataReload(dtOptions.tableId);
            });
        });
    </script>

    <form id="addForm" class="mainForm">
        <div class="modal modal-blur fade" id="modal-create" tabindex="-1" role="dialog" aria-hidden="true"
             data-bs-backdrop="static">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Akun Kas Keluar</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-4">
                        <fieldset class="form-fieldset">
                            <div class="mb-3">
                                <label class="form-label required" for="kode_akun">Kode</label>
                                <input type="text" class="form-control" name="kode_akun" id="kode_akun"
                                       autocomplete="off" placeholder="Kode" maxlength="5" required>
                                <div class="invalid-feedback" role="alert"><strong></strong></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label required" for="nama_akun">Nama Akun Kas Keluar</label>
                                <input type="text" class="form-control" id="nama_akun" name="nama_akun"
                                       autocomplete="off" placeholder="Nama Akun Kas Keluar" maxlength="50" required>
                                <div class="invalid-feedback" role="alert"><strong></strong></div>
                            </div>
                        </fieldset>
                    </div>
                    <div class="modal-footer">
                        <div class="w-100">
                            <div class="row">
                                <div class="col">
                                    <input type="reset" value="Batal" class="btn btn-outline-secondary w-100"
                                           data-bs-dismiss="modal">
                                </div>
                                <div class="col">
                                    <input type="submit" value="Simpan Data" class="btn btn-primary w-100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll(".mainForm").forEach(form => {
                form.addEventListener("submit", function (e) {
                    e.preventDefault();
                    loadingAlert();
                    const formId = this.id;
                    let formData = new FormData(this);
                    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    formData.append("_token", csrfToken);

                    clearErrorMessages(formId);
                    fetch("{{ $storeUrl }}", {
                        method: "POST",
                        headers: {'X-CSRF-TOKEN': csrfToken},
                        body: formData
                    })
                        .then(response => {
                            if (!response.ok) {
                                return response.json().then(err => {
                                    throw {status: response.status, error: err};
                                });
                            }
                            return response.json();
                        })
                        .then(data => {
                            document.getElementById(formId).reset();
                            successAlert(data.message);
                            dataReload("main_table");
                            document.querySelector(`#${formId} [data-bs-dismiss="modal"]`)?.click();
                        })
                        .catch(error => {
                            if (error.status === 422) {
                                const errors = error.error.error || error.error.errors;
                                errorAlert(error.error.message);
                                if (errors) processErrors(errors);
                            } else {
                                const errorMessages = {
                                    419: "Permintaan gagal diproses. Silakan coba lagi.",
                                    500: "Tidak dapat terhubung ke server, Silahkan periksa koneksi internet anda",
                                    403: "Anda tidak memiliki izin untuk mengakses halaman ini",
                                    404: "Halaman tidak ditemukan"
                                };
                                errorAlert(errorMessages[error.status] || "Terjadi kesalahan, silahkan coba memuat ulang halaman");
                            }
                        });
                });
            });

            document.querySelectorAll(".modal").forEach(modal => {
                modal.addEventListener('hidden.bs.modal', function () {
                    const form = modal.closest("form");
                    if (!form) return;
                    form.reset();
                    clearErrorMessages(form.id);
                });
            });
        });
    </script>
@endsection
