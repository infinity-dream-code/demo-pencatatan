@extends('layouts.admin_new')
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{ $dataTitle ?? 'Setting Batasan Kartu' }}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a>
        </li>
        <li class="breadcrumb-item">smartCARD</li>
        <li class="breadcrumb-item active">Setting Batasan Kartu</li>
    </ul>

    @if (session('smartcard_success'))
        <div class="alert alert-success">{{ session('smartcard_success') }}</div>
    @endif
    @if (session('smartcard_error'))
        <div class="alert alert-danger">{{ session('smartcard_error') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Form Batasan Kartu</h5></div>
        <div class="card-body">
            <form id="formSearch" method="GET" action="{{ route('admin.smartcard.setting-batasan-saku.index') }}">
                <input type="hidden" name="search" value="1">

                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label" for="periodeInput">Periode</label>
                        <input type="month" class="form-control" id="periodeInput" name="periode"
                               value="{{ old('periode', $periode ?? '') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="batasBelanjaInput">Batas Belanja Hari</label>
                        <input type="text" class="form-control js-formatted-number" id="batasBelanjaInput"
                               name="batas_belanja_hari"
                               value="{{ old('batas_belanja_hari', $batasBelanjaHari ?? '') }}"
                               placeholder="0" inputmode="numeric">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="batasCashInput">Batas Cash</label>
                        <input type="text" class="form-control js-formatted-number" id="batasCashInput"
                               name="batas_cash"
                               value="{{ old('batas_cash', $batasCash ?? '') }}"
                               placeholder="0" inputmode="numeric">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="aktifInput">Aktif</label>
                        <select class="form-select" id="aktifInput" name="aktif">
                            <option value="" @selected(old('aktif', $aktif ?? '') === '')>— Pilih status —</option>
                            <option value="1" @selected((string) old('aktif', $aktif ?? '') === '1')>Aktif</option>
                            <option value="0" @selected((string) old('aktif', $aktif ?? '') === '0')>Tidak Aktif</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-outline-primary">Lihat</button>
                        <button type="submit" class="btn btn-primary" form="formSave">Simpan</button>
                        <a href="{{ route('admin.smartcard.setting-batasan-saku.index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>

            <form id="formSave" method="POST" action="{{ route('admin.smartcard.setting-batasan-saku.store') }}">
                @csrf
                <input type="hidden" name="periode" id="periodeSave">
                <input type="hidden" name="batas_belanja_hari" id="batasBelanjaSave">
                <input type="hidden" name="batas_cash" id="batasCashSave">
                <input type="hidden" name="aktif" id="aktifSave" value="0">
            </form>

            <p class="text-muted small mt-3 mb-0">Secara default batasan akan diberlakukan secara harian.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Batasan</h5>
            @if (($isSearch ?? false) && ($periode ?? '') !== '')
                <small class="text-muted">periode {{ $periode }}</small>
            @endif
        </div>
        <div class="table-responsive" style="max-height:480px;">
            <table class="table table-sm table-bordered table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:64px;">No</th>
                        <th>Periode</th>
                        <th>Batas Belanja Hari</th>
                        <th>Batas Cash</th>
                        <th style="width:120px;">Aktif</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (($batasanRows ?? null) as $index => $row)
                        <tr>
                            <td>{{ ($batasanRows->firstItem() ?? 0) + $index }}</td>
                            <td>
                                @php
                                    $p = trim((string) ($row->periode ?? ''));
                                    $periodeLabel = (strlen($p) === 6 && ctype_digit($p))
                                        ? substr($p, 0, 4) . '-' . substr($p, 4, 2)
                                        : ($p !== '' ? $p : '—');
                                @endphp
                                {{ $periodeLabel }}
                            </td>
                            <td>{{ number_format((int) ($row->batas_belanja_hari ?? 0), 0, ',', '.') }}</td>
                            <td>{{ number_format((int) ($row->batas_cash ?? 0), 0, ',', '.') }}</td>
                            <td>
                                @if ((int) ($row->aktif ?? 0) === 1)
                                    <span class="badge bg-success">Aktif</span>
                                @else
                                    <span class="badge bg-secondary">Tidak Aktif</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Data batasan tidak ditemukan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (isset($batasanRows) && method_exists($batasanRows, 'hasPages') && $batasanRows->hasPages())
            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                <small class="text-muted">
                    Menampilkan {{ $batasanRows->firstItem() ?? 0 }} sampai {{ $batasanRows->lastItem() ?? 0 }}
                    dari {{ number_format($batasanRows->total() ?? 0, 0, ',', '.') }} entri
                </small>
                <div>
                    {{ $batasanRows->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @endif
    </div>

    <script>
        (function () {
            const periodeInput = document.getElementById('periodeInput');
            const batasBelanjaInput = document.getElementById('batasBelanjaInput');
            const batasCashInput = document.getElementById('batasCashInput');
            const aktifInput = document.getElementById('aktifInput');
            const periodeSave = document.getElementById('periodeSave');
            const batasBelanjaSave = document.getElementById('batasBelanjaSave');
            const batasCashSave = document.getElementById('batasCashSave');
            const aktifSave = document.getElementById('aktifSave');

            function digitsOnly(value) {
                return String(value || '').replace(/[^\d]/g, '');
            }

            function formatNumberInput(el) {
                const raw = digitsOnly(el.value);
                el.value = raw ? parseInt(raw, 10).toLocaleString('id-ID') : '';
            }

            document.querySelectorAll('.js-formatted-number').forEach(function (el) {
                if (el.value && /^\d+$/.test(el.value)) {
                    el.value = parseInt(el.value, 10).toLocaleString('id-ID');
                }
                el.addEventListener('input', function () { formatNumberInput(el); });
            });

            function syncSaveFields() {
                if (periodeSave && periodeInput) periodeSave.value = periodeInput.value;
                if (batasBelanjaSave && batasBelanjaInput) batasBelanjaSave.value = digitsOnly(batasBelanjaInput.value);
                if (batasCashSave && batasCashInput) batasCashSave.value = digitsOnly(batasCashInput.value);
                if (aktifSave && aktifInput) aktifSave.value = aktifInput.value !== '' ? aktifInput.value : '';
            }

            document.getElementById('formSave')?.addEventListener('submit', function (e) {
                syncSaveFields();
                if (!periodeSave?.value || digitsOnly(periodeSave.value).length !== 6) {
                    e.preventDefault();
                    alert('Periode wajib dipilih (tahun dan bulan).');
                    return;
                }
                if (batasBelanjaSave?.value === '') {
                    e.preventDefault();
                    alert('Batas belanja harian wajib diisi.');
                    return;
                }
                if (batasCashSave?.value === '') {
                    e.preventDefault();
                    alert('Batas cash wajib diisi.');
                    return;
                }
                if (aktifSave?.value !== '0' && aktifSave?.value !== '1') {
                    e.preventDefault();
                    alert('Status aktif wajib dipilih.');
                }
            });

            syncSaveFields();
            [periodeInput, batasBelanjaInput, batasCashInput, aktifInput].forEach(function (el) {
                if (el) {
                    el.addEventListener('change', syncSaveFields);
                    el.addEventListener('input', syncSaveFields);
                }
            });
        })();
    </script>
@endsection
