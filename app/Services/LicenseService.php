<?php

namespace App\Services;

use App\Models\AppLicense;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LicenseService
{
    protected string $serverUrl;

    protected ?string $defaultKey;

    public function __construct()
    {
        $this->serverUrl = Cache::get('app_license_server_url') ?: rtrim(config('services.license.server_url', 'https://api.digitaltekno.web.id/api/v1'), '/');
        $this->defaultKey = config('services.license.key', null);
    }

    public function getServerUrl(): string
    {
        return $this->serverUrl;
    }

    /**
     * Standardized HTTP client with IPv4 enforcement and resilient connection timeouts.
     */
    protected function httpClient(int $timeout = 30)
    {
        return Http::withoutVerifying()
            ->connectTimeout(25)
            ->timeout($timeout)
            ->withOptions([
                'version' => 1.1,
            ]);
    }

    /**
     * Get permanent Hardware ID (HWID) physically tied to this computer.
     * Combines Motherboard UUID, Processor ID, and Primary Disk Serial.
     * Remains identical even if Windows / Laragon is re-installed.
     */
    public function getHardwareId(): string
    {
        static $cachedHwid = null;
        if ($cachedHwid !== null) {
            return $cachedHwid;
        }

        if (Cache::has('app_hardware_id')) {
            $cachedHwid = Cache::get('app_hardware_id');

            return $cachedHwid;
        }

        $components = [];

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            try {
                $cmd = 'powershell -NoProfile -NonInteractive -Command "(Get-CimInstance Win32_ComputerSystemProduct).UUID; (Get-CimInstance Win32_Processor).ProcessorId; (Get-CimInstance Win32_DiskDrive | Select-Object -First 1).SerialNumber"';
                $output = @shell_exec($cmd);
                if (! empty($output)) {
                    $lines = preg_split('/[\r\n]+/', trim($output));
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (! empty($line) && strlen($line) > 3 && $line !== 'FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF') {
                            $components[] = $line;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::debug('HWID PowerShell inspection exception: '.$e->getMessage());
            }
        } else {
            if (file_exists('/etc/machine-id')) {
                $components[] = trim(file_get_contents('/etc/machine-id'));
            } elseif (file_exists('/var/lib/dbus/machine-id')) {
                $components[] = trim(file_get_contents('/var/lib/dbus/machine-id'));
            }
        }

        if (empty($components)) {
            $components[] = php_uname('n');
            $components[] = php_uname('m');
            $components[] = gethostname() ?: 'client-pc';
        }

        $seed = implode('-', $components);
        $hash = strtoupper(hash('sha256', $seed));
        $hwid = 'HWID-'.substr($hash, 0, 4).'-'.substr($hash, 4, 4).'-'.substr($hash, 8, 4).'-'.substr($hash, 12, 4);

        $cachedHwid = $hwid;
        Cache::forever('app_hardware_id', $hwid);

        try {
            Storage::disk('local')->put('machine_id.txt', $hwid);
        } catch (\Throwable $e) {
            // Ignore storage exception
        }

        return $hwid;
    }

    /**
     * Backward-compatible alias for getHardwareId().
     */
    public function getMachineId(): string
    {
        return $this->getHardwareId();
    }

    /**
     * Check backend cloud by HWID to automatically restore license and branding.
     * Useful when software is fresh-installed or after database reset.
     */
    public function checkAndRestoreFromHwid(?string $fallbackKey = null): array
    {
        $hwid = $this->getHardwareId();
        $deviceName = gethostname() ?: 'Warehouse-Workstation';

        try {
            // Check dedicated lookup-hwid endpoint first
            $url = "{$this->serverUrl}/license/lookup-hwid";
            $response = $this->httpClient(15)->post($url, [
                'machine_id' => $hwid,
                'device_name' => $deviceName,
            ]);

            // If 404/not routed on remote server yet, try verify with machine_id
            if ($response->status() === 404 && (str_contains($response->body(), 'Route') || str_contains($response->body(), 'not found'))) {
                $response = $this->httpClient(15)->post("{$this->serverUrl}/license/verify", [
                    'machine_id' => $hwid,
                    'device_name' => $deviceName,
                ]);
            }

            if ($response->successful()) {
                $payload = $response->json();
                $data = $payload['data'] ?? [];
                $licenseKey = $data['license_key'] ?? null;

                if (! empty($licenseKey)) {
                    $customAppName = $data['branding']['custom_app_name'] ?? $data['custom_app_name'] ?? null;
                    $customWarehouseName = $data['branding']['custom_warehouse_name'] ?? $data['custom_warehouse_name'] ?? $data['warehouse_name'] ?? null;
                    $customLogoUrl = $data['branding']['custom_logo_url'] ?? $data['custom_logo_url'] ?? null;
                    $clientName = $data['client_name'] ?? $data['client']['name'] ?? $data['branding']['client_name'] ?? null;
                    $clientEmail = $data['client_email'] ?? $data['client']['email'] ?? null;
                    $aiConfig = $data['ai_config'] ?? null;

                    $appLicense = AppLicense::updateOrCreate(
                        ['license_key' => $licenseKey],
                        [
                            'status' => $data['status'] ?? 'active',
                            'plan' => $data['plan'] ?? 'Pro',
                            'client_name' => $clientName,
                            'client_email' => $clientEmail,
                            'custom_app_name' => $customAppName,
                            'custom_warehouse_name' => $customWarehouseName,
                            'custom_logo_url' => $customLogoUrl,
                            'allowed_modules' => $data['allowed_modules'] ?? [],
                            'max_users' => $data['max_users'] ?? 1,
                            'max_devices' => $data['max_devices'] ?? 1,
                            'expires_at' => ! empty($data['expires_at']) ? $data['expires_at'] : null,
                            'is_lifetime' => ! empty($data['is_lifetime']),
                            'last_synced_at' => now(),
                            'raw_data' => $data,
                        ]
                    );

                    Cache::put('app_license_status', $appLicense->status, 3600);
                    Cache::put('app_custom_name', $customAppName, 3600);
                    Cache::put('app_custom_warehouse', $customWarehouseName, 3600);
                    Cache::put('app_custom_logo', $customLogoUrl, 3600);
                    if (! empty($aiConfig)) {
                        Cache::forever('app_ai_config', $aiConfig);
                    }

                    return [
                        'success' => true,
                        'message' => 'Hardware ID dikenali! Lisensi dan branding berhasil dipulihkan secara otomatis.',
                        'license' => $appLicense,
                    ];
                }
            }

            // If lookup by HWID alone was not found, try activating with candidate key (fallback key, local DB key, or config default key)
            $candidateKey = $fallbackKey ?: ($this->getLocalLicense()?->license_key ?: $this->defaultKey);
            if (! empty($candidateKey)) {
                $syncResult = $this->verifyAndSync($candidateKey);
                if ($syncResult['success']) {
                    return [
                        'success' => true,
                        'message' => 'Hardware ID berhasil didaftarkan dan lisensi aktif berhasil dipulihkan!',
                        'license' => $syncResult['license'] ?? null,
                    ];
                }
            }

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Perangkat (HWID) belum terdaftar dengan lisensi aktif di cloud.',
                'status_code' => $response->status(),
            ];
        } catch (\Throwable $e) {
            Log::warning('Gagal auto-restore lisensi via HWID: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Koneksi ke backend server gagal saat memeriksa HWID: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get active license record from local database.
     */
    public function getLocalLicense(?string $key = null): ?AppLicense
    {
        $targetKey = $key ?: $this->defaultKey;
        if ($targetKey) {
            $license = AppLicense::where('license_key', $targetKey)->first();
            if ($license) {
                return $license;
            }
        }

        return AppLicense::latest()->first();
    }

    /**
     * Verify license with Backend Cloud and sync custom branding.
     */
    public function verifyAndSync(?string $key = null, ?string $serverUrl = null): array
    {
        // Support flexible argument order: verifyAndSync(url, key) or verifyAndSync(key, url)
        if (! empty($key) && (str_starts_with($key, 'http://') || str_starts_with($key, 'https://'))) {
            $temp = $key;
            $key = $serverUrl;
            $serverUrl = $temp;
        }

        if (! empty($serverUrl)) {
            $cleanedUrl = rtrim($serverUrl, '/');
            if (! str_contains($cleanedUrl, '/api/v1')) {
                $cleanedUrl .= '/api/v1';
            }
            $this->serverUrl = $cleanedUrl;
            Cache::forever('app_license_server_url', $this->serverUrl);
        }

        $licenseKey = $key ?: ($this->getLocalLicense()?->license_key ?: $this->defaultKey);

        if (empty($licenseKey)) {
            // Attempt auto-restore via HWID
            $hwidResult = $this->checkAndRestoreFromHwid();
            if ($hwidResult['success']) {
                return $hwidResult;
            }

            return [
                'success' => false,
                'message' => 'Kunci lisensi belum diatur di aplikasi dan Hardware ID (HWID) belum terdaftar di cloud.',
            ];
        }

        $machineId = $this->getMachineId();
        $deviceName = gethostname() ?: 'Warehouse-Client';

        try {
            // 1. Attempt to activate/bind this Hardware ID to the license on cloud server
            $activateRes = $this->httpClient(15)->post("{$this->serverUrl}/license/activate", [
                'license_key' => $licenseKey,
                'machine_id' => $machineId,
                'device_name' => $deviceName,
                'app_version' => '1.0.0',
                'os_info' => php_uname('s').' '.php_uname('r'),
            ]);

            $response = $activateRes;

            // 2. Fallback to /verify if /activate is not supported (404)
            if ($response->status() === 404) {
                $response = $this->httpClient(15)->post("{$this->serverUrl}/license/verify", [
                    'license_key' => $licenseKey,
                    'machine_id' => $machineId,
                    'device_name' => $deviceName,
                ]);
            }

            if ($response->successful()) {
                $payload = $response->json();
                $data = $payload['data'] ?? [];

                // Extract custom branding and client info
                $customAppName = $data['branding']['custom_app_name'] ?? $data['custom_app_name'] ?? null;
                $customWarehouseName = $data['branding']['custom_warehouse_name'] ?? $data['custom_warehouse_name'] ?? $data['warehouse_name'] ?? null;
                $customLogoUrl = $data['branding']['custom_logo_url'] ?? $data['custom_logo_url'] ?? null;
                $clientName = $data['client_name'] ?? $data['client']['name'] ?? $data['branding']['client_name'] ?? null;
                $clientEmail = $data['client_email'] ?? $data['client']['email'] ?? null;
                $aiConfig = $data['ai_config'] ?? null;

                $appLicense = AppLicense::updateOrCreate(
                    ['license_key' => $licenseKey],
                    [
                        'status' => $data['status'] ?? 'active',
                        'plan' => $data['plan'] ?? 'Pro',
                        'client_name' => $clientName,
                        'client_email' => $clientEmail,
                        'custom_app_name' => $customAppName,
                        'custom_warehouse_name' => $customWarehouseName,
                        'custom_logo_url' => $customLogoUrl,
                        'allowed_modules' => $data['allowed_modules'] ?? [],
                        'max_users' => $data['max_users'] ?? 1,
                        'max_devices' => $data['max_devices'] ?? 1,
                        'expires_at' => ! empty($data['expires_at']) ? $data['expires_at'] : null,
                        'is_lifetime' => ! empty($data['is_lifetime']),
                        'last_synced_at' => now(),
                        'raw_data' => $data,
                    ]
                );

                // Update cache
                Cache::put('app_license_status', $appLicense->status, 3600);
                Cache::put('app_custom_name', $customAppName, 3600);
                Cache::put('app_custom_warehouse', $customWarehouseName, 3600);
                Cache::put('app_custom_logo', $customLogoUrl, 3600);
                if (! empty($aiConfig)) {
                    Cache::forever('app_ai_config', $aiConfig);
                }

                return [
                    'success' => true,
                    'message' => 'Lisensi & Hardware ID (HWID) berhasil diaktivasi dan disinkronkan!',
                    'license' => $appLicense,
                ];
            } else {
                $errorData = $response->json();
                $message = $errorData['message'] ?? 'Verifikasi atau aktivasi lisensi gagal pada server backend.';

                // Update local status if record exists
                $local = AppLicense::where('license_key', $licenseKey)->first();
                if ($local) {
                    $local->update(['last_synced_at' => now()]);
                }

                return [
                    'success' => false,
                    'message' => $message,
                    'status_code' => $response->status(),
                ];
            }
        } catch (\Throwable $e) {
            Log::error('Gagal menghubungi License Server Backend: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Koneksi ke License Cloud Server gagal: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get effective shop/app name.
     */
    public function getEffectiveAppName(): string
    {
        $license = $this->getLocalLicense();
        if ($license && ! empty($license->custom_app_name)) {
            return $license->custom_app_name;
        }

        return config('app.name', 'Vendora Shopee Management');
    }

    /**
     * Get effective shop/app logo URL.
     */
    public function getEffectiveLogoUrl(): ?string
    {
        $license = $this->getLocalLicense();
        if ($license && ! empty($license->custom_logo_url)) {
            return $license->custom_logo_url;
        }

        return null;
    }

    /**
     * Get active dynamic AI configuration synchronized from Backend 2.
     */
    public function getAiConfig(): ?array
    {
        $cached = Cache::get('app_ai_config');
        if (! empty($cached) && is_array($cached)) {
            return $cached;
        }

        $license = $this->getLocalLicense();
        if ($license && ! empty($license->raw_data['ai_config'])) {
            return $license->raw_data['ai_config'];
        }

        // Attempt live fetch from Backend 2
        try {
            $res = $this->httpClient(10)->get("{$this->serverUrl}/ai/config");
            if ($res->successful()) {
                $cfg = $res->json('data');
                if ($cfg) {
                    Cache::put('app_ai_config', $cfg, 3600);

                    return $cfg;
                }
            }
        } catch (\Throwable $e) {
            // silent fallback
        }

        return null;
    }

    /**
     * Periodic background heartbeat check to backend cloud.
     * Throttled (runs at most once every 1 hour) and resilient to network/internet drops.
     * If internet is offline, preserves active license status under offline grace period.
     * If cloud explicitly responds with revoked/expired/suspended, updates local status immediately.
     */
    public function checkHeartbeatThrottled(): array
    {
        $license = $this->getLocalLicense();
        if (! $license) {
            return [
                'has_license' => false,
                'valid' => false,
                'reason' => 'Belum ada lisensi terpasang.',
            ];
        }

        // Throttle heartbeat to once per hour to avoid slowing down requests
        $cacheKey = 'app_license_heartbeat_last_check';
        if (Cache::has($cacheKey)) {
            return [
                'has_license' => true,
                'valid' => $license->isValid(),
                'throttled' => true,
                'is_offline' => $license->isOfflineGraceActive(),
            ];
        }

        // Lock for 1 hour
        Cache::put($cacheKey, now()->toIso8601String(), 3600);

        $machineId = $this->getMachineId();
        $licenseKey = $license->license_key;

        try {
            // Heartbeat request with short connection timeout (8s) so we don't slow down the user
            $response = $this->httpClient(8)->post("{$this->serverUrl}/license/heartbeat", [
                'license_key' => $licenseKey,
                'machine_id' => $machineId,
                'app_version' => '1.0.0',
            ]);

            // Fallback to /verify if /heartbeat is 404
            if ($response->status() === 404) {
                $response = $this->httpClient(8)->post("{$this->serverUrl}/license/verify", [
                    'license_key' => $licenseKey,
                    'machine_id' => $machineId,
                ]);
            }

            if ($response->successful()) {
                $payload = $response->json();
                $data = $payload['data'] ?? [];
                $serverStatus = $data['status'] ?? ($payload['status'] ?? 'active');

                // Update local status with cloud status
                $license->update([
                    'status' => $serverStatus,
                    'last_synced_at' => now(),
                    'expires_at' => ! empty($data['expires_at']) ? $data['expires_at'] : $license->expires_at,
                    'is_lifetime' => isset($data['is_lifetime']) ? (bool) $data['is_lifetime'] : $license->is_lifetime,
                ]);

                Cache::put('app_license_status', $serverStatus, 3600);

                return [
                    'has_license' => true,
                    'valid' => $license->isValid(),
                    'status' => $serverStatus,
                    'online' => true,
                ];
            }

            // If server explicitly returned 401, 403, 404 (e.g. revoked, expired, deleted)
            if (in_array($response->status(), [401, 403, 404])) {
                $payload = $response->json();
                $serverStatus = $payload['status'] ?? 'expired';

                $license->update([
                    'status' => $serverStatus,
                    'last_synced_at' => now(),
                ]);

                Cache::put('app_license_status', $serverStatus, 3600);

                return [
                    'has_license' => true,
                    'valid' => false,
                    'status' => $serverStatus,
                    'online' => true,
                    'reason' => $payload['message'] ?? 'Lisensi dinonaktifkan atau kedaluwarsa di cloud.',
                ];
            }

            // Server returned 500 or other unexpected status -> treat as temporary network issue (offline tolerance)
            Log::info("Cloud License Heartbeat Server Warning [HTTP {$response->status()}]. Retaining local valid state.");

            return [
                'has_license' => true,
                'valid' => $license->isValid(),
                'is_offline' => true,
                'status' => $license->status,
            ];
        } catch (\Throwable $e) {
            // NETWORK TIMEOUT / NO INTERNET / HOST UNREACHABLE
            // PENGKONDISIAN JARINGAN LEMAH:
            // JANGAN matikan lisensi! Pertahankan validitas lokal dalam batas offline grace period.
            Log::info('Cloud License Heartbeat Network Unreachable: '.$e->getMessage().'. Running in offline grace period.');

            return [
                'has_license' => true,
                'valid' => $license->isValid(),
                'is_offline' => true,
                'status' => $license->status,
                'grace_days_left' => $license->daysUntilOfflineExpiry(),
            ];
        }
    }
}

