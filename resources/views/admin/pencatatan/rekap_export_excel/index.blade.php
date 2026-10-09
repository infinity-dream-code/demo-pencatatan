@extends('layouts.admin_new')
@section('style')
    <link rel="stylesheet" href="{{asset('main/libs/datatables-bs5/datatables.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/select2/select2.css')}}">
    <style>
        .cek-saldo-box {
            min-width: 220px;
            border: 2px solid var(--bs-primary);
            border-radius: .5rem;
            padding: .75rem 1rem;
            text-align: center;
            background: rgba(var(--bs-primary-rgb), .06);
        }

        .cek-saldo-box .label {
            font-weight: 700;
            letter-spacing: .04em;
            color: var(--bs-primary);
        }

        .cek-saldo-box .value {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--bs-primary);
        }
    </style>
@endsection

@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{ $dataTitle ?? 'Rekap Export Excel' }}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a>
        </li>
        <li class="breadcrumb-item">{{ $title ?? 'Pencatatan Sederhana' }}</li>
        <li class="breadcrumb-item active">{{ $mainTitle ?? 'Rekap Export Excel' }}</li>
    </ul>

    <div class="card mb-4">
        <div class="card-header header-elements">
            <h5 class="mb-0 me-2">{{ $dataTitle ?? 'Rekap export excel Kas Masuk dan Kas Keluar' }}</h5>
            <div class="card-header-elements ms-auto">
                <button type="button" class="btn btn-success" id="btn-export-excel">
                    <span class="ri-file-excel-2-line me-2"></span>Export Excel
                </button>
            </div>
        </div>
        <div class="card-body">
            <form id="filterForm">
                <fieldset class="form-fieldset">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label" for="filter_periode">Tahun Periode</label>
                            <select class="form-select" id="filter_periode" name="filter[periode]" data-control="select2">
                                <option value="all">Semua</option>
                                @foreach($periodes as $periode)
                                    <option value="{{ $periode }}" @selected(($defaultPeriode ?? '') === $periode)>{{ $periode }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="filter_no_bukti">No Bukti</label>
                            <input type="text" class="form-control" id="filter_no_bukti" name="filter[no_bukti]"
                                   placeholder="No Bukti" autocomplete="off">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="filter_keterangan">Keterangan</label>
                            <input type="text" class="form-control" id="filter_keterangan" name="filter[keterangan]"
                                   placeholder="Keterangan" autocomplete="off">
                        </div>
                        <div class="col-md-3">
                            <div class="cek-saldo-box">
                                <div class="label">SALDO</div>
                                <div class="value" id="saldo-value">0</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="filter_tanggal_dari">Tanggal Dari</label>
                            <input type="date" class="form-control" id="filter_tanggal_dari" name="filter[tanggal_dari]">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="filter_tanggal_sampai">Tanggal Sampai</label>
                            <input type="date" class="form-control" id="filter_tanggal_sampai" name="filter[tanggal_sampai]">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="filter_akun_masuk">Akun Kas Masuk</label>
                            <select class="form-select" id="filter_akun_masuk" name="filter[akun_masuk]" data-control="select2">
                                <option value="all">Semua</option>
                                @foreach($akunMasuk as $item)
                                    <option value="{{ $item->NamaAkunMasuk }}">{{ $item->NamaAkunMasuk }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="filter_akun_keluar">Akun Kas Keluar</label>
                            <select class="form-select" id="filter_akun_keluar" name="filter[akun_keluar]" data-control="select2">
                                <option value="all">Semua</option>
                                @foreach($akunKeluar as $item)
                                    <option value="{{ $item->NamaAkunKeluar }}">{{ $item->NamaAkunKeluar }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="reset" class="btn btn-secondary">
                            <span class="ri-reset-left-line me-2"></span>Kosongkan filter kolom atas
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <span class="ri-search-line me-2"></span>Cari
                        </button>
                    </div>
                </fieldset>
            </form>
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
    <script src="{{asset('main/libs/select2/select2.min.js')}}"></script>

    <script type="text/javascript">
        const dtOptions = {
            tableId: 'main_table',
            formId: 'filterForm',
            columnUrl: '{{ $columnsUrl ?? null }}',
            dataUrl: '{{ $datasUrl ?? null }}',
            dataColumns: [],
            thead: true,
            tfoot: false,
            paging: true,
            searching: true,
            scrollX: true,
            fixedHeader: false,
            pageLength: 25,
            lengthMenu: [10, 25, 50, 75, 100],
            order: [[1, 'asc']],
            buttons: ['excel'],
        };

        function formatSaldo(value) {
            return Number(value || 0).toLocaleString('id-ID');
        }

        function buildExportUrl() {
            const params = new URLSearchParams($(document.getElementById('filterForm')).serialize());
            return `{{ $exportUrl }}?${params.toString()}`;
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll("[data-control='select2']").forEach(select => {
                let wrapper = document.createElement('div');
                wrapper.classList.add('position-relative');
                select.parentNode.insertBefore(wrapper, select);
                wrapper.appendChild(select);
                $(select).select2({
                    placeholder: 'Pilih satu',
                    language: 'id',
                    dropdownParent: $(wrapper),
                    width: '100%',
                });
            });

            if (dtOptions.dataUrl && dtOptions.columnUrl) {
                getDT(dtOptions);

                const filterForm = document.getElementById(dtOptions.formId);
                filterForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    dataReFilter(dtOptions.tableId);
                });
                filterForm.addEventListener('reset', function () {
                    setTimeout(function () {
                        $('#filter_periode, #filter_akun_masuk, #filter_akun_keluar').val('all').trigger('change');
                        dataReFilter(dtOptions.tableId);
                    }, 0);
                });
            }

            $('#main_table').on('xhr.dt', function (e, settings, json) {
                document.getElementById('saldo-value').textContent = formatSaldo(json?.saldo ?? 0);
            });

            document.getElementById('btn-export-excel').addEventListener('click', function () {
                window.location.href = buildExportUrl();
            });
        });
    </script>
@endsection
