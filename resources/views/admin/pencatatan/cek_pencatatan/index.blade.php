@extends('layouts.admin_new')
@section('style')
    <link rel="stylesheet" href="{{asset('main/libs/datatables-bs5/datatables.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/select2/select2.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/bootstrap-datepicker/bootstrap-datepicker.css')}}">
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

        table.dataTable tbody tr.selected,
        table.dataTable tbody tr.cek-row-active {
            background: rgba(var(--bs-primary-rgb), .12) !important;
        }

        .cek-detail-card {
            border: 1px solid #d9dee3;
            border-radius: .5rem;
            padding: 1rem;
            height: 100%;
        }

        .cek-bukti-preview {
            width: 100%;
            min-height: 280px;
            max-height: 420px;
            object-fit: contain;
            border: 1px solid #d9dee3;
            border-radius: .5rem;
            background: #f8f9fa;
        }

        .cek-bukti-empty {
            min-height: 280px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px dashed #cfd3d8;
            border-radius: .5rem;
            color: #8592a3;
            background: #f8f9fa;
        }
    </style>
@endsection

@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{ $dataTitle ?? 'Cek Pencatatan' }}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a>
        </li>
        <li class="breadcrumb-item">{{ $title ?? 'Pencatatan Sederhana' }}</li>
        <li class="breadcrumb-item active">{{ $mainTitle ?? 'Cek Pencatatan' }}</li>
    </ul>

    <div class="card mb-4">
        <div class="card-header header-elements">
            <h5 class="mb-0 me-2">{{ $dataTitle ?? 'Cek Pencatatan Kas Masuk dan Kas Keluar' }}</h5>
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

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="cek-detail-card">
                <div class="mb-2"><strong>No Bukti :</strong> <span id="detail-no-bukti">-</span></div>
                <div class="mb-2">
                    <strong>URL :</strong>
                    <a href="#" id="detail-url" target="_blank" rel="noopener" class="text-break">-</a>
                </div>
                <button type="button" class="btn btn-link px-0" id="btn-cek-foto">Cek lagi foto bukti nya</button>
            </div>
        </div>
        <div class="col-lg-4">
            <div id="bukti-empty" class="cek-bukti-empty">Belum ada foto bukti dipilih</div>
            <img id="bukti-preview" class="cek-bukti-preview d-none" src="" alt="Foto bukti">
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
        };

        function formatSaldo(value) {
            const number = Number(value || 0);
            return number.toLocaleString('id-ID');
        }

        function setDetail(row) {
            const noBukti = row?.no_bukti || '-';
            const url = row?.buktiurl && row.buktiurl !== '-' ? row.buktiurl : '';

            document.getElementById('detail-no-bukti').textContent = noBukti;
            const urlEl = document.getElementById('detail-url');
            const preview = document.getElementById('bukti-preview');
            const empty = document.getElementById('bukti-empty');

            if (url) {
                urlEl.textContent = url;
                urlEl.href = url;
                preview.src = url;
                preview.classList.remove('d-none');
                empty.classList.add('d-none');
            } else {
                urlEl.textContent = '-';
                urlEl.removeAttribute('href');
                preview.src = '';
                preview.classList.add('d-none');
                empty.classList.remove('d-none');
            }
        }

        function clearDetail() {
            setDetail(null);
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
                        clearDetail();
                    }, 0);
                });
            }

            $('#main_table').on('xhr.dt', function (e, settings, json) {
                document.getElementById('saldo-value').textContent = formatSaldo(json?.saldo ?? 0);
            });

            $('#main_table tbody').on('click', 'tr', function () {
                const table = DT[dtOptions.tableId];
                if (!table) return;

                const rowData = table.row(this).data();
                if (!rowData) return;

                $('#main_table tbody tr').removeClass('cek-row-active');
                $(this).addClass('cek-row-active');
                setDetail(rowData);
            });

            document.getElementById('btn-cek-foto').addEventListener('click', function () {
                const urlEl = document.getElementById('detail-url');
                const href = urlEl.getAttribute('href');
                if (!href) {
                    warningAlert('Foto bukti tidak tersedia untuk baris ini');
                    return;
                }
                const preview = document.getElementById('bukti-preview');
                preview.src = href + (href.includes('?') ? '&' : '?') + 't=' + Date.now();
                preview.classList.remove('d-none');
                document.getElementById('bukti-empty').classList.add('d-none');
            });
        });
    </script>
@endsection
