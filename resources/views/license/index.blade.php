@extends('layouts.adminlte')

@section('title', 'Lisensi & Branding Toko')
@section('page_title', 'Lisensi & Branding Toko')

@section('content')

@if(!$license)
    <div class="alert alert-warning border-left border-warning shadow-sm mb-4 py-3">
        <div class="d-flex align-items-center">
            <i class="fas fa-lock fa-2x text-warning mr-3"></i>
            <div>
                <h6 class="font-weight-bold mb-1 text-dark">Aplikasi Belum Teraktivasi!</h6>
                <p class="mb-0 text-muted text-sm">
                    Seluruh fitur gudang, katalog produk, kasir, dan packing saat ini dikunci. Silakan masukkan kunci lisensi Anda pada formulir di bawah ini untuk mengaktifkan sistem.
                </p>
            </div>
        </div>
    </div>
@elseif(!$license->isValid())
    <div class="alert alert-danger border-left border-danger shadow-sm mb-4 py-3">
        <div class="d-flex align-items-center">
            <i class="fas fa-ban fa-2x text-danger mr-3"></i>
            <div>
                <h6 class="font-weight-bold mb-1 text-dark">Akses Fitur Terkunci (Lisensi Tidak Aktif)</h6>
                <p class="mb-0 text-dark text-sm">
                    {{ $license->getInvalidReason() }}
                </p>
            </div>
        </div>
    </div>
@elseif($license->isOfflineGraceActive())
    <div class="alert alert-info border-left border-info shadow-sm mb-4 py-3">
        <div class="d-flex align-items-center">
            <i class="fas fa-satellite-dish fa-2x text-info mr-3"></i>
            <div>
                <h6 class="font-weight-bold mb-1 text-dark">Mode Toleransi Offline Aktif</h6>
                <p class="mb-0 text-muted text-sm">
                    Koneksi ke Server Cloud belum tersambung dalam 24 jam terakhir. Sistem tetap dapat digunakan secara normal dengan batas toleransi verifikasi tersisa <strong>{{ $license->daysUntilOfflineExpiry() }} hari</strong>.
                </p>
            </div>
        </div>
    </div>
@endif

<div class="row">
    <!-- License Status Card -->
    <div class="col-lg-8 col-12">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <h5 class="card-title font-weight-bold mb-0 text-dark">
                    <i class="fas fa-certificate text-warning mr-2"></i> Status Lisensi Aplikasi
                </h5>
                @if($license && $license->isValid())
                    <span class="badge badge-success px-3 py-1 font-weight-bold" style="font-size: 12px;">
                        <i class="fas fa-check-circle mr-1"></i> AKTIF & TERVERIFIKASI
                    </span>
                @else
                    <span class="badge badge-danger px-3 py-1 font-weight-bold" style="font-size: 12px;">
                        <i class="fas fa-exclamation-triangle mr-1"></i> BELUM AKTIF / PERLU SINKRONISASI
                    </span>
                @endif
            </div>

            <div class="card-body">
                @if($license)
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted text-uppercase mb-1" style="font-size: 11px; font-weight: 700;">Kunci Lisensi</label>
                            <div class="font-weight-bold font-monospace text-primary" style="font-size: 16px;">
                                {{ $license->license_key }}
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted text-uppercase mb-1" style="font-size: 11px; font-weight: 700;">Paket Layanan</label>
                            <div>
                                <span class="badge badge-info px-2.5 py-1 font-weight-bold text-uppercase" style="font-size: 12px;">
                                    {{ $license->plan }}
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted text-uppercase mb-1" style="font-size: 11px; font-weight: 700;">Nama Klien / Perusahaan</label>
                            <div class="font-weight-bold text-dark">
                                {{ $license->client_name ?: 'Tidak Diketahui' }}
                                @if($license->client_email)
                                    <span class="text-muted d-block" style="font-size: 12px;">{{ $license->client_email }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted text-uppercase mb-1" style="font-size: 11px; font-weight: 700;">Masa Berlaku Lisensi</label>
                            <div class="font-weight-bold">
                                @if($license->is_lifetime)
                                    <span class="text-purple"><i class="fas fa-infinity mr-1"></i> Lifetime (Permanen)</span>
                                @elseif($license->expires_at)
                                    <span class="{{ $license->expires_at->isPast() ? 'text-danger' : 'text-dark' }}">
                                        {{ $license->expires_at->translatedFormat('d F Y H:i') }}
                                        <small class="text-muted">({{ $license->expires_at->diffForHumans() }})</small>
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted text-uppercase mb-1" style="font-size: 11px; font-weight: 700;">Batas Pengguna & Perangkat</label>
                            <div class="font-weight-bold text-dark">
                                <i class="fas fa-users mr-1 text-muted"></i> Maks {{ $license->max_users }} Pengguna &nbsp;|&nbsp;
                                <i class="fas fa-desktop mr-1 text-muted"></i> Maks {{ $license->max_devices }} Perangkat
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted text-uppercase mb-1" style="font-size: 11px; font-weight: 700;">Sinkronisasi Terakhir</label>
                            <div class="text-muted" style="font-size: 13px;">
                                <i class="fas fa-history mr-1"></i>
                                {{ $license->last_synced_at ? $license->last_synced_at->translatedFormat('d F Y H:i:s') : 'Belum pernah sinkron' }}
                            </div>
                        </div>
                    </div>

                    <!-- Allowed Modules -->
                    <div class="border-top pt-3 mb-4">
                        <label class="text-muted text-uppercase mb-2" style="font-size: 11px; font-weight: 700;">Modul Fitur Aktif</label>
                        <div class="d-flex flex-wrap" style="gap: 6px;">
                            @forelse($license->allowed_modules ?? [] as $module)
                                <span class="badge badge-light border px-2.5 py-1 text-dark" style="font-size: 11px;">
                                    <i class="fas fa-check text-success mr-1"></i> {{ ucwords(str_replace('_', ' ', $module)) }}
                                </span>
                            @empty
                                <span class="text-muted" style="font-size: 12px;">Tidak ada modul terdaftar.</span>
                            @endforelse
                        </div>
                    </div>
                @else
                    <div class="alert alert-info py-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <i class="fas fa-info-circle mr-2"></i> Belum ada data lisensi lokal tersimpan.
                            <span class="d-block mt-1 text-muted" style="font-size: 12px;">
                                Jika perangkat ini pernah diaktivasi, Anda dapat langsung memulihkan lisensi menggunakan Hardware ID (HWID) fisik tanpa mengetik ulang.
                            </span>
                        </div>
                        <button type="button" id="btnRestoreHwid" class="btn btn-sm btn-outline-info font-weight-bold shadow-sm">
                            <i class="fas fa-magic mr-1"></i> Pulihkan Otomatis via HWID
                        </button>
                    </div>
                @endif

                <!-- Sync Form -->
                <div class="bg-light p-4 rounded-lg border">
                    <form id="licenseSyncForm" action="{{ route('license.sync') }}" method="POST">
                        @csrf
                        <div class="form-row">
                            <div class="form-group col-12 mb-3">
                                <label class="font-weight-bold text-dark d-flex justify-content-between align-items-center" style="font-size: 13px;">
                                    <span>Kunci Lisensi Klien <span class="text-danger">*</span></span>
                                    <small class="text-muted font-normal">Format: <code>MDN-XXXX-XXXX-XXXX</code></small>
                                </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-white border-right-0"><i class="fas fa-key text-primary"></i></span>
                                    </div>
                                    <input type="text" name="license_key" id="license_key" class="form-control font-monospace font-weight-bold text-sm border-left-0"
                                           value="{{ $license?->license_key ?? $defaultKey }}"
                                           placeholder="Masukkan kunci lisensi Anda..." required>
                                </div>
                                <small class="form-text text-muted">
                                    Kunci lisensi unik yang Anda peroleh dari Member Area atau Admin di Panel 3.
                                </small>
                            </div>

                            <!-- Server URL (Hidden / Optional custom toggle) -->
                            <div class="form-group col-12 mb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <label class="text-muted mb-1" style="font-size: 11px;">
                                        Server Cloud: <strong class="text-dark">{{ $serverUrl }}</strong>
                                    </label>
                                    <a href="javascript:void(0)" class="text-primary text-decoration-none" style="font-size: 11px;"
                                       onclick="document.getElementById('customServerGroup').classList.toggle('d-none');">
                                        <i class="fas fa-cog mr-1"></i> Ubah Server URL (Opsional)
                                    </a>
                                </div>
                                <div id="customServerGroup" class="d-none mt-2">
                                    <input type="url" name="server_url" class="form-control font-monospace text-xs"
                                           value="{{ $serverUrl }}"
                                           placeholder="https://api.digitaltekno.web.id/api/v1">
                                    <small class="form-text text-muted">
                                        Biarkan default <code>https://api.digitaltekno.web.id/api/v1</code> kecuali Anda menjalankan server backend lokal.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div id="syncFeedbackAlert" class="alert d-none py-2 px-3 mb-3 text-sm"></div>

                        <div class="d-flex justify-content-between align-items-center pt-3 border-top flex-wrap gap-2">
                            <button type="button" id="btnHwidCheck" class="btn btn-outline-secondary btn-sm font-weight-bold">
                                <i class="fas fa-microchip mr-1"></i> Cek Lisensi via HWID
                            </button>
                            <button type="submit" id="btnSubmitSync" class="btn btn-primary font-weight-bold px-4 shadow-sm">
                                <span class="spinner-border spinner-border-sm d-none mr-1" role="status" aria-hidden="true"></span>
                                <i class="fas fa-sync-alt mr-1 btn-icon"></i> <span class="btn-text">Sinkronisasi & Aktifkan Lisensi</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Shop Branding, AI & Device Info Column -->
    <div class="col-lg-4 col-12">
        <!-- Live Branding Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="card-title font-weight-bold mb-0 text-dark">
                    <i class="fas fa-store text-success mr-2"></i> Branding Toko Aktif
                </h6>
            </div>
            <div class="card-body text-center py-4">
                <div class="mb-3 mx-auto shadow-sm rounded-circle d-flex align-items-center justify-content-center overflow-hidden border bg-white"
                     style="width: 80px; height: 80px;">
                    @if(!empty($license?->effective_logo_url))
                        <img src="{{ $license?->effective_logo_url }}" alt="Logo Toko" style="width: 100%; height: 100%; object-fit: cover;">
                    @elseif(!empty($currentAppName) && $currentAppName !== 'Vendora Shopee Management')
                        <img src="https://ui-avatars.com/api/?name={{ urlencode($currentAppName) }}&background=0D9488&color=fff&bold=true&rounded=true" alt="Logo Toko" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='{{ asset('images/logo.png') }}'">
                    @else
                        <img src="{{ asset('images/logo.png') }}" alt="Default Logo" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='https://ui-avatars.com/api/?name=Vendora&background=0D9488&color=fff'">
                    @endif
                </div>

                <h5 class="font-weight-bold text-dark mb-1">
                    {{ $license?->effective_app_name ?? ($currentAppName ?? 'Vendora Shopee Management') }}
                </h5>
                <p class="text-primary font-weight-bold mb-1" style="font-size: 13px;">
                    <i class="fas fa-warehouse mr-1 text-info"></i> {{ $license?->effective_warehouse_name ?? ($currentWarehouseName ?? 'Gudang Utama') }}
                </p>
                <p class="text-muted mb-3" style="font-size: 12px;">
                    @if(!empty($license?->custom_app_name) || !empty($license?->custom_warehouse_name))
                        <span class="badge badge-success px-2 py-0.5">White-label Custom</span>
                    @elseif(!empty($license?->client_name))
                        <span class="badge badge-info px-2 py-0.5">Nama Toko Klien (Lisensi)</span>
                    @else
                        <span class="badge badge-secondary px-2 py-0.5">Default App Name</span>
                    @endif
                </p>

                <div class="alert alert-light border text-left p-2 mb-0" style="font-size: 11.5px;">
                    <i class="fas fa-lightbulb text-warning mr-1"></i>
                    Nama dan logo toko ini diatur oleh administrator melalui <strong>Panel Admin Cloud</strong> dan otomatis diterapkan pada seluruh antarmuka aplikasi lokal ini.
                </div>
            </div>
        </div>

        <!-- Hardware ID (HWID) Information Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="card-title font-weight-bold mb-0 text-dark">
                    <i class="fas fa-fingerprint text-primary mr-2"></i> Hardware ID (HWID Fisik)
                </h6>
                <span class="badge badge-pill badge-primary px-2" style="font-size: 10px;">Permanen</span>
            </div>
            <div class="card-body" style="font-size: 12.5px;">
                <div class="mb-2">
                    <span class="text-muted d-block font-weight-bold text-uppercase" style="font-size: 10px;">HWID Mesin Ini:</span>
                    <div class="d-flex align-items-center justify-content-between bg-light p-2 rounded border mt-1">
                        <code id="hwidValue" class="font-weight-bold text-dark font-monospace" style="font-size: 13px;">{{ $machineId }}</code>
                        <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" onclick="copyHwid()" title="Salin HWID">
                            <i class="fas fa-copy"></i> Salin
                        </button>
                    </div>
                </div>
                <div class="mb-2">
                    <span class="text-muted d-block font-weight-bold text-uppercase" style="font-size: 10px;">Nama Komputer (Hostname):</span>
                    <span class="font-weight-bold text-dark">{{ gethostname() ?: 'Warehouse-Client' }}</span>
                </div>
                <div class="p-2 rounded bg-light border text-muted mt-2" style="font-size: 11px;">
                    <i class="fas fa-shield-alt text-success mr-1"></i>
                    <strong>Anti-Reset:</strong> HWID ini dibuat dari serial Motherboard, CPU, dan Harddisk fisik. Jika Laragon atau Windows di-install ulang, HWID tetap identik dan lisensi otomatis pulih.
                </div>
            </div>
        </div>

        <!-- Centralized AI Cloud Settings Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="card-title font-weight-bold mb-0 text-dark">
                    <i class="fas fa-robot text-purple mr-2"></i> AI Advisor Terpusat (Cloud)
                </h6>
            </div>
            <div class="card-body" style="font-size: 12px;">
                <div class="mb-2">
                    <span class="text-muted d-block font-weight-bold text-uppercase" style="font-size: 10px;">Model AI Terpilih:</span>
                    <span class="badge badge-purple px-2 py-1 font-monospace">
                        {{ $aiConfig['model'] ?? 'qmodel_38max' }}
                    </span>
                </div>
                <div class="mb-2">
                    <span class="text-muted d-block font-weight-bold text-uppercase" style="font-size: 10px;">Base URL AI Gateway:</span>
                    <span class="text-break text-muted font-monospace" style="font-size: 11px;">
                        {{ $aiConfig['base_url'] ?? 'https://vendorarouter.web.id/api/v1' }}
                    </span>
                </div>
                <div class="mb-2">
                    <span class="text-muted d-block font-weight-bold text-uppercase" style="font-size: 10px;">Status Kunci AI:</span>
                    @if(!empty($aiConfig['api_key']))
                        <span class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Terhubung ke Cloud Gateway</span>
                    @else
                        <span class="text-warning font-weight-bold"><i class="fas fa-exclamation-circle mr-1"></i> Menggunakan Default Lokal</span>
                    @endif
                </div>
                <small class="text-muted d-block mt-2">
                    <i class="fas fa-info-circle mr-1"></i> Pengaturan AI diatur secara sentral di <strong>Panel Admin (3)</strong> dan ditarik otomatis oleh komputer lokal ini.
                </small>
            </div>
        </div>
    </div>
</div>

<script>
function copyHwid() {
    const text = document.getElementById('hwidValue').innerText.trim();
    navigator.clipboard.writeText(text).then(function() {
        alert('Hardware ID (HWID) berhasil disalin: ' + text);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('licenseSyncForm');
    const submitBtn = document.getElementById('btnSubmitSync');
    const spinner = submitBtn.querySelector('.spinner-border');
    const btnIcon = submitBtn.querySelector('.btn-icon');
    const btnText = submitBtn.querySelector('.btn-text');
    const alertBox = document.getElementById('syncFeedbackAlert');
    const btnHwidCheck = document.getElementById('btnHwidCheck');
    const btnRestoreHwid = document.getElementById('btnRestoreHwid');

    function setLoading(isLoading) {
        submitBtn.disabled = isLoading;
        if (isLoading) {
            spinner.classList.remove('d-none');
            btnIcon.classList.add('d-none');
            btnText.innerText = 'Menghubungkan ke Cloud...';
        } else {
            spinner.classList.add('d-none');
            btnIcon.classList.remove('d-none');
            btnText.innerText = 'Sinkronisasi & Aktifkan Lisensi';
        }
    }

    function showAlert(type, message) {
        alertBox.className = 'alert py-2 px-3 mb-3 text-sm alert-' + type;
        alertBox.innerHTML = message;
        alertBox.classList.remove('d-none');
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        setLoading(true);
        alertBox.classList.add('d-none');

        const formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json().then(data => ({ status: res.status, data })))
        .then(({ status, data }) => {
            setLoading(false);
            if (status >= 200 && status < 300 && data.success) {
                showAlert('success', '<strong><i class="fas fa-check-circle mr-1"></i> Sukses!</strong> ' + data.message + '<div class="mt-1 text-xs">Memuat ulang antarmuka aplikasi secara otomatis...</div>');
                setTimeout(() => {
                    window.location.reload();
                }, 1200);
            } else {
                showAlert('danger', '<strong><i class="fas fa-exclamation-triangle mr-1"></i> Gagal:</strong> ' + (data.message || 'Verifikasi lisensi gagal.'));
            }
        })
        .catch(err => {
            setLoading(false);
            showAlert('danger', '<strong><i class="fas fa-times-circle mr-1"></i> Error:</strong> Gagal terhubung ke server. Silakan periksa koneksi internet Anda.');
        });
    });

    function triggerHwidRestore() {
        setLoading(true);
        showAlert('info', '<i class="fas fa-spinner fa-spin mr-1"></i> Memeriksa pendaftaran Hardware ID di database cloud...');

        const licenseKeyInput = document.getElementById('license_key');
        const licenseKey = licenseKeyInput ? licenseKeyInput.value.trim() : '';

        fetch('{{ route("license.restore-hwid") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                license_key: licenseKey
            })
        })
        .then(res => res.json().then(data => ({ status: res.status, data })))
        .then(({ status, data }) => {
            setLoading(false);
            if (status >= 200 && status < 300 && data.success) {
                showAlert('success', '<strong><i class="fas fa-check-circle mr-1"></i> HWID Dikenali!</strong> ' + data.message + '<div class="mt-1 text-xs">Memuat ulang aplikasi secara otomatis...</div>');
                setTimeout(() => {
                    window.location.reload();
                }, 1200);
            } else {
                showAlert('warning', '<strong><i class="fas fa-info-circle mr-1"></i> Info:</strong> ' + (data.message || 'HWID belum terdaftar dengan lisensi aktif. Masukkan kunci lisensi di atas untuk aktivasi pertama kali.'));
            }
        })
        .catch(err => {
            setLoading(false);
            showAlert('danger', 'Gagal memeriksa HWID ke server cloud.');
        });
    }

    if (btnHwidCheck) btnHwidCheck.addEventListener('click', triggerHwidRestore);
    if (btnRestoreHwid) btnRestoreHwid.addEventListener('click', triggerHwidRestore);
});
</script>
@endsection
