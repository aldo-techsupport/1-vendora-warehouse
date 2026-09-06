@extends('layouts.adminlte')

@section('title', 'Lisensi & Branding Toko')
@section('page_title', 'Lisensi & Branding Toko')

@section('content')
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
                    <div class="alert alert-info py-3 mb-4">
                        <i class="fas fa-info-circle mr-2"></i> Belum ada data lisensi lokal yang tersimpan. Masukkan kunci lisensi di bawah dan klik <strong>Sinkronisasi Lisensi Sekarang</strong> untuk mengaktifkan aplikasi.
                    </div>
                @endif

                <!-- Sync Form -->
                <div class="bg-light p-3 rounded border">
                    <form action="{{ route('license.sync') }}" method="POST">
                        @csrf
                        <div class="form-row">
                            <div class="form-group col-md-6 mb-2">
                                <label class="font-weight-bold text-dark" style="font-size: 13px;">
                                    URL Server Backend (Base URL dari Panel 3) <span class="text-danger">*</span>
                                </label>
                                <input type="url" name="server_url" class="form-control font-monospace text-xs"
                                       value="{{ $serverUrl }}"
                                       placeholder="http://127.0.0.1:8000/api/v1 atau domain cloud" required>
                                <small class="form-text text-muted">
                                    Base URL API backend cloud (dari menu <em>Info Koneksi</em> di Panel 3).
                                </small>
                            </div>
                            <div class="form-group col-md-6 mb-2">
                                <label class="font-weight-bold text-dark" style="font-size: 13px;">
                                    Kunci Lisensi Klien <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="license_key" class="form-control font-monospace font-weight-bold text-xs"
                                       value="{{ $license?->license_key ?? $defaultKey }}"
                                       placeholder="Contoh: MDN-XXXX-XXXX-XXXX-XXXX" required>
                                <small class="form-text text-muted">
                                    Kunci lisensi unik yang diterbitkan oleh administrator di Panel 3.
                                </small>
                            </div>
                        </div>

                        <div class="text-right pt-2 border-top">
                            <button type="submit" class="btn btn-primary font-weight-bold px-4">
                                <i class="fas fa-sync-alt mr-1"></i> Sinkronisasi Lisensi & Pengaturan Sekarang
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
                    @if(!empty($license?->custom_logo_url))
                        <img src="{{ $license?->custom_logo_url }}" alt="Logo Toko" style="width: 100%; height: 100%; object-fit: cover;">
                    @else
                        <img src="{{ asset('images/logo.png') }}" alt="Default Logo" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($license?->custom_app_name ?? 'Vendora') }}&background=0D9488&color=fff'">
                    @endif
                </div>

                <h5 class="font-weight-bold text-dark mb-1">
                    {{ $license?->custom_app_name ?: ($currentAppName ?? 'Vendora Shopee Management') }}
                </h5>
                <p class="text-muted mb-3" style="font-size: 12px;">
                    @if(!empty($license?->custom_app_name))
                        <span class="badge badge-success px-2 py-0.5">White-label Custom</span>
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

        <!-- Machine Information Card -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="card-title font-weight-bold mb-0 text-dark">
                    <i class="fas fa-desktop text-info mr-2"></i> Identitas Mesin Klien
                </h6>
            </div>
            <div class="card-body" style="font-size: 12.5px;">
                <div class="mb-2">
                    <span class="text-muted d-block font-weight-bold text-uppercase" style="font-size: 10px;">ID Perangkat (Machine ID):</span>
                    <code class="font-weight-bold text-dark">{{ $machineId }}</code>
                </div>
                <div class="mb-2">
                    <span class="text-muted d-block font-weight-bold text-uppercase" style="font-size: 10px;">Nama Komputer (Hostname):</span>
                    <span class="font-weight-bold text-dark">{{ gethostname() ?: 'Warehouse-Client' }}</span>
                </div>
                <div>
                    <span class="text-muted d-block font-weight-bold text-uppercase" style="font-size: 10px;">URL Server Lisensi Aktif:</span>
                    <span class="text-break text-muted">{{ $serverUrl }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
