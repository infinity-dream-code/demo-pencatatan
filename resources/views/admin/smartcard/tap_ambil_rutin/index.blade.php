@extends('layouts.admin_new')
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{ $dataTitle ?? 'TAP Ambil Rutin' }}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a>
        </li>
        <li class="breadcrumb-item">smartCARD</li>
        <li class="breadcrumb-item active">TAP Ambil Rutin</li>
    </ul>

    <div class="sc-page tar-page">
        <div class="card sc-card tar-card">
            <div class="sc-card-body">
                <div class="tar-title">PENGELUARAN UANG SAKU / AMBIL CASH</div>

                <form id="tarForm" autocomplete="off" novalidate>
                    @csrf
                    {{-- Decoy agar browser tidak isi username/password login ke field tap --}}
                    <input type="text" class="tar-autofill-trap" tabindex="-1" aria-hidden="true" autocomplete="username">
                    <input type="password" class="tar-autofill-trap" tabindex="-1" aria-hidden="true" autocomplete="current-password">
                    <div class="tar-panel">
                        <div class="tar-row">
                            <div class="tar-label">TAP ID</div>
                            <div class="tar-value">
                                <input type="text" id="tapId" class="tar-input" inputmode="numeric"
                                       autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                                       readonly>
                            </div>
                        </div>
                        <div class="tar-row">
                            <div class="tar-label">Nama</div>
                            <div class="tar-value tar-value-readonly">
                                <input type="text" id="nama" class="tar-input tar-input-readonly" readonly tabindex="-1" autocomplete="off">
                            </div>
                        </div>
                        <div class="tar-row">
                            <div class="tar-label">SALDO</div>
                            <div class="tar-value tar-value-saldo">
                                <input type="text" id="saldo" class="tar-input tar-input-saldo" readonly tabindex="-1" autocomplete="off" value="0">
                            </div>
                        </div>
                        <div class="tar-row">
                            <div class="tar-label tar-label-ambil">AMBIL</div>
                            <div class="tar-value tar-value-ambil">
                                <input type="text" id="ambil" class="tar-input tar-input-ambil" inputmode="numeric"
                                       placeholder="0" autocomplete="off">
                            </div>
                        </div>
                        <div class="tar-row tar-row-end">
                            <div class="tar-label">PIN</div>
                            <div class="tar-value">
                                <input type="password" id="pin" class="tar-input" inputmode="numeric" maxlength="20"
                                       autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="maxAmbil" value="0">
                    <input type="hidden" id="batasCash" value="0">
                </form>

                <div id="tarInfo" class="tar-info" hidden>
                    Batas cash: <strong id="tarInfoBatas">0</strong>
                    &nbsp;|&nbsp; Maks. ambil: <strong id="tarInfoMax">0</strong>
                </div>
            </div>
        </div>
    </div>

    <style>
        .tar-page { max-width: 720px; margin: 0 auto; }
        .tar-card { border: 2px solid #c4b5fd; }
        .tar-title {
            font-family: 'Sora', sans-serif;
            font-size: 20px;
            font-weight: 800;
            color: #5b21b6;
            text-align: center;
            margin-bottom: 24px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .tar-panel {
            border: 2px solid #7c3aed;
            border-radius: 4px;
            overflow: hidden;
            background: #fff;
        }
        .tar-row {
            display: grid;
            grid-template-columns: 160px 1fr;
            border-bottom: 2px solid #7c3aed;
            min-height: 72px;
        }
        .tar-row-end { border-bottom: 0; }
        .tar-label {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(180deg, #7c3aed 0%, #6d28d9 100%);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 12px;
        }
        .tar-label-ambil { color: #fef08a; }
        .tar-value {
            display: flex;
            align-items: center;
            background: #e0f2fe;
            padding: 8px 16px;
        }
        .tar-value-readonly { background: #f0f9ff; }
        .tar-value-saldo { background: #fff; }
        .tar-value-ambil { background: #fff; }
        .tar-input {
            width: 100%;
            border: 0;
            background: transparent;
            font-size: 28px;
            font-weight: 700;
            color: #1e3a8a;
            outline: none;
            font-family: 'Sora', sans-serif;
        }
        .tar-input-readonly {
            font-size: 22px;
            font-weight: 600;
            color: #1f2937;
        }
        .tar-input-saldo {
            font-size: 42px;
            font-weight: 800;
            color: #dc2626;
            text-align: left;
        }
        .tar-input-ambil {
            font-size: 42px;
            font-weight: 800;
            color: #2563eb;
        }
        .tar-info {
            margin-top: 16px;
            padding: 12px 16px;
            background: #faf5ff;
            border: 1px solid #e9d5ff;
            border-radius: 10px;
            font-size: 14px;
            color: #5b21b6;
            text-align: center;
        }
        .tar-autofill-trap {
            position: absolute;
            width: 0;
            height: 0;
            opacity: 0;
            pointer-events: none;
            overflow: hidden;
        }
        @media (max-width: 600px) {
            .tar-row { grid-template-columns: 110px 1fr; min-height: 60px; }
            .tar-label { font-size: 16px; }
            .tar-input-saldo, .tar-input-ambil { font-size: 32px; }
        }
    </style>

    <script>
        (function () {
            const lookupUrl = @json(route('admin.smartcard.tap-ambil-rutin.lookup'));
            const processUrl = @json(route('admin.smartcard.tap-ambil-rutin.process'));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const tapId = document.getElementById('tapId');
            const nama = document.getElementById('nama');
            const saldo = document.getElementById('saldo');
            const ambil = document.getElementById('ambil');
            const pin = document.getElementById('pin');
            const maxAmbil = document.getElementById('maxAmbil');
            const batasCash = document.getElementById('batasCash');
            const tarInfo = document.getElementById('tarInfo');
            const tarInfoBatas = document.getElementById('tarInfoBatas');
            const tarInfoMax = document.getElementById('tarInfoMax');
            const form = document.getElementById('tarForm');

            let cardLoaded = false;
            let lookingUp = false;
            let suppressBlurLookup = false;
            let lastFailedTapId = '';

            const enableTapInput = function () {
                tapId.removeAttribute('readonly');
            };

            tapId.addEventListener('focus', enableTapInput);
            tapId.addEventListener('click', enableTapInput);

            // Bersihkan autofill browser (username login) saat halaman load
            window.setTimeout(function () {
                if (!cardLoaded && tapId.value.trim() !== '') {
                    tapId.value = '';
                }
                pin.value = '';
                tapId.focus();
            }, 100);

            const formatRp = function (n) {
                return String(Math.max(0, parseInt(n, 10) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            };
            const parseNum = function (v) {
                return parseInt(String(v || '').replace(/\D/g, ''), 10) || 0;
            };

            const focusTapIdSafe = function () {
                suppressBlurLookup = true;
                window.setTimeout(function () {
                    tapId.focus();
                    window.setTimeout(function () {
                        suppressBlurLookup = false;
                    }, 300);
                }, 50);
            };

            const alertMsg = function (msg, focusTap) {
                alert(msg);
                if (focusTap) focusTapIdSafe();
            };

            const resetCard = function (clearTapId) {
                cardLoaded = false;
                nama.value = '';
                saldo.value = '0';
                ambil.value = '';
                pin.value = '';
                maxAmbil.value = '0';
                batasCash.value = '0';
                tarInfo.hidden = true;
                if (clearTapId) {
                    tapId.value = '';
                }
            };

            const doLookup = async function () {
                const id = tapId.value.trim();
                if (!id) {
                    resetCard(false);
                    return;
                }
                // Jangan ulang lookup kartu yang baru gagal / sedang diproses
                if (lookingUp) return;
                if (id === lastFailedTapId) {
                    tapId.value = '';
                    lastFailedTapId = '';
                    return;
                }

                lookingUp = true;
                try {
                    const res = await fetch(lookupUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ tap_id: id }),
                        credentials: 'same-origin',
                    });
                    const json = await res.json();

                    if (!json.ok) {
                        lastFailedTapId = id;
                        resetCard(true);
                        lookingUp = false;
                        alertMsg(json.message || 'Gagal membaca kartu.', true);
                        return;
                    }

                    lastFailedTapId = '';
                    const d = json.data || {};
                    cardLoaded = true;
                    nama.value = d.nama || '';
                    saldo.value = formatRp(d.saldo || 0);
                    maxAmbil.value = String(d.max_ambil || 0);
                    batasCash.value = String(d.batas_cash || 0);
                    tarInfoBatas.textContent = formatRp(d.batas_cash || 0);
                    tarInfoMax.textContent = formatRp(d.max_ambil || 0);
                    tarInfo.hidden = false;
                    ambil.focus();
                } catch (e) {
                    lastFailedTapId = id;
                    resetCard(true);
                    alertMsg('Gagal menghubungi server.', true);
                } finally {
                    lookingUp = false;
                }
            };

            const doProcess = async function () {
                const id = tapId.value.trim();
                const nominal = parseNum(ambil.value);
                const pinVal = pin.value.trim();
                const maxVal = parseNum(maxAmbil.value);
                const saldoVal = parseNum(saldo.value);

                if (!id || !cardLoaded) {
                    alertMsg('Tap kartu terlebih dahulu.', true);
                    return;
                }
                if (!nominal) {
                    alertMsg('Isi nominal AMBIL.', false);
                    ambil.focus();
                    return;
                }
                if (!pinVal) {
                    alertMsg('Isi PIN kartu.', false);
                    pin.focus();
                    return;
                }
                if (nominal > saldoVal) {
                    alertMsg('Saldo tidak mencukupi.', false);
                    ambil.focus();
                    return;
                }
                if (maxVal > 0 && nominal > maxVal) {
                    alertMsg('Nominal melebihi batas cash / saldo.', false);
                    ambil.focus();
                    return;
                }

                try {
                    const res = await fetch(processUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            tap_id: id,
                            ambil: String(nominal),
                            pin: pinVal,
                        }),
                        credentials: 'same-origin',
                    });
                    const json = await res.json();

                    if (!json.ok) {
                        if (json.pin_error) {
                            alertMsg(json.message || 'PIN salah.', false);
                            pin.focus();
                        } else if (json.blocked) {
                            lastFailedTapId = id;
                            resetCard(true);
                            alertMsg(json.message || 'Kartu terblokir.', true);
                        } else {
                            alertMsg(json.message || 'Transaksi gagal.', false);
                            ambil.focus();
                        }
                        return;
                    }

                    const d = json.data || {};
                    alert(
                        (json.message || 'Berhasil') + '\n' +
                        'Nama: ' + (d.nama || '') + '\n' +
                        'Ambil: Rp ' + formatRp(d.ambil || 0) + '\n' +
                        'Saldo baru: Rp ' + formatRp(d.saldo_baru || 0)
                    );

                    form.reset();
                    resetCard(true);
                    lastFailedTapId = '';
                    focusTapIdSafe();
                } catch (e) {
                    alertMsg('Gagal memproses transaksi.', false);
                    ambil.focus();
                }
            };

            tapId.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (!cardLoaded) {
                        doLookup();
                    } else if (!ambil.value.trim()) {
                        doLookup();
                    }
                }
            });

            // Saat user ketik/tap ulang, izinkan lookup lagi
            tapId.addEventListener('input', function () {
                lastFailedTapId = '';
            });

            tapId.addEventListener('blur', function () {
                if (suppressBlurLookup || lookingUp) return;
                if (tapId.value.trim() && !cardLoaded) {
                    doLookup();
                }
            });

            ambil.addEventListener('input', function () {
                const raw = parseNum(ambil.value);
                ambil.value = raw > 0 ? formatRp(raw) : '';
            });

            pin.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    doProcess();
                }
            });

            ambil.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    pin.focus();
                }
            });

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const id = tapId.value.trim();
                const nominal = parseNum(ambil.value);

                if (id && !cardLoaded) {
                    doLookup();
                    return;
                }
                if (id && cardLoaded && nominal > 0) {
                    doProcess();
                    return;
                }
                if (id) {
                    doLookup();
                }
            });
        })();
    </script>
@endsection
