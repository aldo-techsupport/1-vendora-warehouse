<?php

namespace App\Services;

use App\Models\ShopeeSetting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopeeCloudSyncService
{
    public function __construct(
        protected LicenseService $licenseService,
        protected StockMutationService $mutationService
    ) {}

    /**
     * Pull and process pending Shopee events from Central Backend Cloud.
     */
    public function syncPendingEvents(?string $customServerUrl = null, ?string $customLicenseKey = null): array
    {
        $serverUrl = rtrim($customServerUrl ?: $this->licenseService->getServerUrl(), '/');
        $license = $this->licenseService->getLocalLicense($customLicenseKey);
        $licenseKey = $license?->license_key ?? config('services.license.key');

        if (empty($licenseKey)) {
            return [
                'success' => false,
                'message' => 'Lisensi belum aktif. Silakan masukkan Lisensi pada menu Pengaturan Lisensi terlebih dahulu.',
                'processed' => 0,
            ];
        }

        $shopeeSetting = ShopeeSetting::current();
        $shopId = $shopeeSetting->shop_id;

        $endpoint = "{$serverUrl}/client/shopee/events";

        try {
            $response = Http::withoutVerifying()
                ->timeout(20)
                ->get($endpoint, array_filter([
                    'license_key' => $licenseKey,
                    'shop_id' => $shopId,
                    'limit' => 50,
                ]));

            if (! $response->successful()) {
                $errorMsg = $response->json('message') ?? ('Server Cloud merespons dengan HTTP ' . $response->status());
                Log::warning("Shopee Cloud Sync Failed: {$errorMsg}");

                return [
                    'success' => false,
                    'message' => "Gagal menghubungi Cloud Gateway: {$errorMsg}",
                    'processed' => 0,
                ];
            }

            $events = $response->json('events') ?? [];
            if (empty($events)) {
                return [
                    'success' => true,
                    'message' => 'Tidak ada notifikasi pesanan baru dari Shopee Cloud Gateway.',
                    'processed' => 0,
                ];
            }

            $processedCount = 0;
            $errors = [];

            foreach ($events as $event) {
                $eventId = $event['id'];
                $payload = $event['payload'] ?? [];
                $dataContent = $payload['data'] ?? [];
                $orderSn = $event['order_sn'] ?? ($dataContent['order_sn'] ?? null);

                try {
                    if (! empty($orderSn)) {
                        $orderData = [
                            'order_sn' => $orderSn,
                            'shop_id' => $event['shop_id'] ?? $shopId,
                            'order_status' => $event['order_status'] ?? ($dataContent['status'] ?? 'READY_TO_SHIP'),
                            'total_amount' => $dataContent['total_amount'] ?? ($payload['total_amount'] ?? 0),
                            'buyer_username' => $dataContent['buyer_username'] ?? ($payload['buyer_username'] ?? 'Shopee Customer'),
                            'items' => $dataContent['items'] ?? ($payload['items'] ?? []),
                        ];

                        // Deduct warehouse stock and record stock mutation
                        $this->mutationService->processShopeeOrder($orderData);
                    }

                    // Acknowledge back to Cloud Gateway
                    Http::withoutVerifying()
                        ->timeout(10)
                        ->post("{$serverUrl}/client/shopee/events/{$eventId}/ack", [
                            'license_key' => $licenseKey,
                            'success' => true,
                        ]);

                    $processedCount++;
                } catch (Exception $e) {
                    Log::error("Failed to process Shopee Event #{$eventId}: " . $e->getMessage());
                    $errors[] = "Event #{$eventId}: {$e->getMessage()}";

                    // Report failure to cloud
                    Http::withoutVerifying()
                        ->timeout(10)
                        ->post("{$serverUrl}/client/shopee/events/{$eventId}/ack", [
                            'license_key' => $licenseKey,
                            'success' => false,
                            'error_message' => $e->getMessage(),
                        ]);
                }
            }

            $msg = "Sinkronisasi selesai! {$processedCount} pesanan Shopee diproses ke stok gudang.";
            if (! empty($errors)) {
                $msg .= ' Namun terjadi beberapa kesalahan: ' . implode('; ', array_slice($errors, 0, 3));
            }

            return [
                'success' => true,
                'message' => $msg,
                'processed' => $processedCount,
                'total_fetched' => count($events),
            ];
        } catch (Exception $e) {
            Log::error("Shopee Cloud Sync Exception: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Terjadi kesalahan koneksi saat sinkronisasi Shopee: ' . $e->getMessage(),
                'processed' => 0,
            ];
        }
    }
}
