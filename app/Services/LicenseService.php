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
        $this->serverUrl = Cache::get('app_license_server_url') ?: rtrim(config('services.license.server_url', 'http://127.0.0.1:8000/api/v1'), '/');
        $this->defaultKey = config('services.license.key', 'MDN-MEDN-WARE-2026-PRO');
    }

    public function getServerUrl(): string
    {
        return $this->serverUrl;
    }

    /**
     * Get machine ID unique to this client installation.
     */
    public function getMachineId(): string
    {
        $path = 'machine_id.txt';
        if (Storage::disk('local')->exists($path)) {
            $id = trim(Storage::disk('local')->get($path));
            if (!empty($id)) {
                return $id;
            }
        }

        $raw = php_uname() . '_' . gethostname() . '_' . (isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : 'local');
        $newId = 'DEV-' . strtoupper(substr(md5($raw), 0, 12));
        Storage::disk('local')->put($path, $newId);

        return $newId;
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
        if (!empty($key) && (str_starts_with($key, 'http://') || str_starts_with($key, 'https://'))) {
            $temp = $key;
            $key = $serverUrl;
            $serverUrl = $temp;
        }

        if (!empty($serverUrl)) {
            $cleanedUrl = rtrim($serverUrl, '/');
            if (!str_contains($cleanedUrl, '/api/v1')) {
                $cleanedUrl .= '/api/v1';
            }
            $this->serverUrl = $cleanedUrl;
            Cache::forever('app_license_server_url', $this->serverUrl);
        }

        $licenseKey = $key ?: $this->defaultKey;

        if (empty($licenseKey)) {
            return [
                'success' => false,
                'message' => 'Kunci lisensi belum diatur di aplikasi.',
            ];
        }

        $machineId = $this->getMachineId();
        $deviceName = gethostname() ?: 'Warehouse-Client';

        try {
            $response = Http::timeout(10)->post("{$this->serverUrl}/license/verify", [
                'license_key' => $licenseKey,
                'machine_id' => $machineId,
                'device_name' => $deviceName,
            ]);

            if ($response->successful()) {
                $payload = $response->json();
                $data = $payload['data'] ?? [];

                // Extract custom branding
                $customAppName = $data['branding']['custom_app_name'] ?? $data['custom_app_name'] ?? null;
                $customLogoUrl = $data['branding']['custom_logo_url'] ?? $data['custom_logo_url'] ?? null;
                $clientName = $data['client']['name'] ?? null;
                $clientEmail = $data['client']['email'] ?? null;
                $aiConfig = $data['ai_config'] ?? null;

                $appLicense = AppLicense::updateOrCreate(
                    ['license_key' => $licenseKey],
                    [
                        'status' => $data['status'] ?? 'active',
                        'plan' => $data['plan'] ?? 'Trial',
                        'client_name' => $clientName,
                        'client_email' => $clientEmail,
                        'custom_app_name' => $customAppName,
                        'custom_logo_url' => $customLogoUrl,
                        'allowed_modules' => $data['allowed_modules'] ?? [],
                        'max_users' => $data['max_users'] ?? 1,
                        'max_devices' => $data['max_devices'] ?? 1,
                        'expires_at' => !empty($data['expires_at']) ? $data['expires_at'] : null,
                        'is_lifetime' => !empty($data['is_lifetime']),
                        'last_synced_at' => now(),
                        'raw_data' => $data,
                    ]
                );

                // Update cache
                Cache::put('app_license_status', $appLicense->status, 3600);
                Cache::put('app_custom_name', $customAppName, 3600);
                Cache::put('app_custom_logo', $customLogoUrl, 3600);
                if (!empty($aiConfig)) {
                    Cache::forever('app_ai_config', $aiConfig);
                }

                return [
                    'success' => true,
                    'message' => 'Lisensi, data branding toko, dan pengaturan AI berhasil disinkronkan!',
                    'license' => $appLicense,
                ];
            } else {
                $errorData = $response->json();
                $message = $errorData['message'] ?? 'Verifikasi lisensi gagal pada server backend.';

                // If machine is not yet authorized/activated, attempt activation
                if ($response->status() === 403 && (str_contains(strtolower($message), 'activate') || str_contains(strtolower($message), 'not authorized'))) {
                    $activateRes = Http::timeout(10)->post("{$this->serverUrl}/license/activate", [
                        'license_key' => $licenseKey,
                        'machine_id' => $machineId,
                        'device_name' => $deviceName,
                        'app_version' => '1.0.0',
                        'os_info' => php_uname('s') . ' ' . php_uname('r'),
                    ]);

                    if ($activateRes->successful()) {
                        $payload = $activateRes->json();
                        $data = $payload['data'] ?? [];

                        $customAppName = $data['branding']['custom_app_name'] ?? $data['custom_app_name'] ?? null;
                        $customLogoUrl = $data['branding']['custom_logo_url'] ?? $data['custom_logo_url'] ?? null;
                        $clientName = $data['client']['name'] ?? null;
                        $clientEmail = $data['client']['email'] ?? null;
                        $aiConfig = $data['ai_config'] ?? null;

                        $appLicense = AppLicense::updateOrCreate(
                            ['license_key' => $licenseKey],
                            [
                                'status' => $data['status'] ?? 'active',
                                'plan' => $data['plan'] ?? 'Trial',
                                'client_name' => $clientName,
                                'client_email' => $clientEmail,
                                'custom_app_name' => $customAppName,
                                'custom_logo_url' => $customLogoUrl,
                                'allowed_modules' => $data['allowed_modules'] ?? [],
                                'max_users' => $data['max_users'] ?? 1,
                                'max_devices' => $data['max_devices'] ?? 1,
                                'expires_at' => !empty($data['expires_at']) ? $data['expires_at'] : null,
                                'is_lifetime' => !empty($data['is_lifetime']),
                                'last_synced_at' => now(),
                                'raw_data' => $data,
                            ]
                        );

                        Cache::put('app_license_status', $appLicense->status, 3600);
                        Cache::put('app_custom_name', $customAppName, 3600);
                        Cache::put('app_custom_logo', $customLogoUrl, 3600);
                        if (!empty($aiConfig)) {
                            Cache::forever('app_ai_config', $aiConfig);
                        }

                        return [
                            'success' => true,
                            'message' => 'Perangkat berhasil diaktivasi, branding toko & AI telah disinkronkan!',
                            'license' => $appLicense,
                        ];
                    } else {
                        $actError = $activateRes->json();
                        $message = $actError['message'] ?? $message;
                    }
                }

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
            Log::error('Gagal menghubungi License Server Backend: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Koneksi ke License Cloud Server gagal: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get effective shop/app name.
     */
    public function getEffectiveAppName(): string
    {
        $license = $this->getLocalLicense();
        if ($license && !empty($license->custom_app_name)) {
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
        if ($license && !empty($license->custom_logo_url)) {
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
        if (!empty($cached) && is_array($cached)) {
            return $cached;
        }

        $license = $this->getLocalLicense();
        if ($license && !empty($license->raw_data['ai_config'])) {
            return $license->raw_data['ai_config'];
        }

        // Attempt live fetch from Backend 2
        try {
            $res = Http::timeout(5)->get("{$this->serverUrl}/ai/config");
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
}
