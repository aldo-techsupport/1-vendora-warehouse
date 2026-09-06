<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Auto-detect and restore license based on machine HWID if not present or freshly installed
        try {
            if (!app()->runningUnitTests()) {
                $hasLocalActive = \Illuminate\Support\Facades\Cache::get('app_license_status') === 'active';
                if (!$hasLocalActive) {
                    $throttleKey = 'hwid_auto_check_throttle';
                    if (!\Illuminate\Support\Facades\Cache::has($throttleKey)) {
                        \Illuminate\Support\Facades\Cache::put($throttleKey, 1, 300); // Check at most every 5 minutes if inactive
                        $existing = \App\Models\AppLicense::where('status', 'active')->first();
                        if (!$existing) {
                            app(\App\Services\LicenseService::class)->checkAndRestoreFromHwid();
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fail safely without disrupting application boot
        }

        \Illuminate\Support\Facades\View::composer('*', function ($view) {
            try {
                $license = \App\Models\AppLicense::latest()->first();
                $appName = ($license && !empty($license->custom_app_name))
                    ? $license->custom_app_name
                    : config('app.name', 'Vendora Shopee Management');
                $warehouseName = ($license && !empty($license->custom_warehouse_name))
                    ? $license->custom_warehouse_name
                    : 'Gudang Utama';
                $appLogo = ($license && !empty($license->custom_logo_url))
                    ? $license->custom_logo_url
                    : null;
                $appLicense = $license;
            } catch (\Throwable $e) {
                $appName = config('app.name', 'Vendora Shopee Management');
                $warehouseName = 'Gudang Utama';
                $appLogo = null;
                $appLicense = null;
            }

            $view->with([
                'currentAppName' => $appName,
                'currentWarehouseName' => $warehouseName,
                'currentAppLogo' => $appLogo,
                'currentLicense' => $appLicense,
            ]);
        });
    }
}
