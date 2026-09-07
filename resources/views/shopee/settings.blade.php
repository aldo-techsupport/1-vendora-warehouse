@extends('layouts.adminlte')

@section('title', 'Pengaturan Shopee Open API')
@section('page_title', 'Konfigurasi Shopee Open API v2')

@section('content')

<div class="row">
    <!-- Main API Settings Form -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-dark">
                    <i class="fas fa-key text-warning mr-1"></i> Kredensial Pengembang (Developer Credentials)
                </h6>
            </div>
            <div class="card-body">
                <form action="{{ route('shopee.settings.update') }}" method="POST">
                    @csrf
                    
                    <div class="form-group">
                        <label class="font-weight-bold">Nama Toko Shopee</label>
                        <input type="text" name="shop_name" class="form-control" value="{{ old('shop_name', $setting->shop_name) }}" placeholder="Contoh: Vendora Official Store">
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Partner ID <span class="text-danger">*</span></label>
                            <input type="number" name="partner_id" class="form-control" value="{{ old('partner_id', $setting->partner_id) }}" placeholder="Contoh: 1005234">
                            <small class="text-muted">Diberikan dari Shopee Open Platform Console.</small>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Shop ID</label>
                            <input type="number" name="shop_id" class="form-control" value="{{ old('shop_id', $setting->shop_id) }}" placeholder="Contoh: 28472918">
                            <small class="text-muted">ID Toko Shopee Anda.</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Partner Key (Secret Key) <span class="text-danger">*</span></label>
                        <input type="text" name="partner_key" class="form-control font-monospace" value="{{ old('partner_key', $setting->partner_key) }}" placeholder="Contoh: 6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d...">
                        <small class="text-muted">Kunci rahasia untuk enkripsi HMAC-SHA256 signature.</small>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Environment / Lingkungan API</label>
                        <select name="environment" class="form-control">
                            <option value="sandbox" {{ $setting->environment === 'sandbox' ? 'selected' : '' }}>🧪 Sandbox (Test & Development)</option>
                            <option value="production" {{ $setting->environment === 'production' ? 'selected' : '' }}>🚀 Production (Live Toko Asli)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="autoSyncStockSwitch" name="auto_sync_stock" value="1" {{ $setting->auto_sync_stock ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-bold text-dark" for="autoSyncStockSwitch">
                                Otomatis Sinkronkan Stok Gudang ke Shopee saat Mutasi
                            </label>
                        </div>
                        <small class="text-muted">Jika diaktifkan, setiap kali stok fisik di Gudang Utama berubah (barang masuk/keluar), sistem akan otomatis mengirim update stok ke Shopee Open API.</small>
                    </div>

                    <hr class="my-4">

                    <button type="submit" class="btn btn-primary font-weight-bold px-4">
                        <i class="fas fa-save mr-1"></i> Simpan Konfigurasi
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Webhook & OAuth Setup Helper via Cloud Gateway -->
    <div class="col-lg-5">
        <!-- Cloud Gateway Status Card -->
        <div class="card shadow-sm border-0 mb-4 border-left-primary">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-dark">
                    <i class="fas fa-cloud text-primary mr-1"></i> Shopee Cloud Gateway Relay
                </h6>
                <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Aktif</span>
            </div>
            <div class="card-body">
                <p class="text-muted text-sm mb-3">
                    Komputer gudang ini terhubung ke <strong>Backend Cloud Gateway</strong>. Server Shopee mengirim notifikasi webhook ke Cloud publik, lalu stok gudang lokal otomatis tersinkronisasi.
                </p>
                <form action="{{ route('shopee.sync-cloud') }}" method="POST" class="mb-2">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary btn-block font-weight-bold py-2 shadow-sm">
                        <i class="fas fa-sync-alt mr-1"></i> Tarik & Sinkron Pesanan Cloud Sekarang
                    </button>
                </form>
                <small class="text-muted d-block text-center">
                    <i class="fas fa-info-circle mr-1"></i> Otomatis berjalan di background via scheduler / cron.
                </small>
            </div>
        </div>

        <!-- OAuth Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-dark">
                    <i class="fas fa-shield-alt text-primary mr-1"></i> Otorisasi Toko (OAuth Callback)
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted text-sm mb-3">
                    Shopee Open API v2 menggunakan OAuth untuk menghubungkan akun toko. Daftarkan URL callback publik berikut:
                </p>

                <div class="form-group">
                    <label class="text-xs text-uppercase font-weight-bold text-muted">Redirect Callback URL (Cloud):</label>
                    <div class="input-group">
                        <input type="text" class="form-control form-control-sm bg-light font-monospace text-xs" value="{{ $callbackUrl }}" readonly id="callbackUrlInput">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="navigator.clipboard.writeText(document.getElementById('callbackUrlInput').value); alert('URL Callback tersalin!')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <small class="text-muted">Daftarkan URL ini pada menu <strong>App Redirect URL</strong> di Shopee Open Platform Console.</small>
                </div>

                @if($authUrl)
                    <a href="{{ $authUrl }}" target="_blank" class="btn btn-shopee btn-block font-weight-bold">
                        <i class="fas fa-external-link-alt mr-1"></i> Buka Otorisasi Shopee
                    </a>
                @else
                    <button class="btn btn-secondary btn-block disabled" disabled>
                        <i class="fas fa-lock mr-1"></i> Masukkan Partner ID & Key untuk Otorisasi
                    </button>
                @endif
            </div>
        </div>

        <!-- Webhook Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-dark">
                    <i class="fas fa-bolt text-danger mr-1"></i> Shopee Webhook Push Listener (Cloud Gateway)
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted text-sm mb-3">
                    Saat terjadi transaksi pembelian di Shopee, Shopee akan mengirim notifikasi webhook ke URL publik ini. Stok di Gudang Utama akan langsung terpotong.
                </p>

                <div class="form-group">
                    <label class="text-xs text-uppercase font-weight-bold text-muted">Webhook Endpoint URL (Cloud):</label>
                    <div class="input-group">
                        <input type="text" class="form-control form-control-sm bg-light font-monospace text-xs" value="{{ $webhookUrl }}" readonly id="webhookUrlInput">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="navigator.clipboard.writeText(document.getElementById('webhookUrlInput').value); alert('URL Webhook tersalin!')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <small class="text-muted">Daftarkan URL publik ini pada menu <strong>Push Mechanism / Webhook URL</strong> di Shopee Open Platform.</small>
                </div>

                <div class="alert alert-light border py-2 text-xs">
                    <i class="fas fa-lock text-success mr-1"></i> Validasi signature HMAC-SHA256 aktif di Cloud Gateway menggunakan <code>partner_key</code>.
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
