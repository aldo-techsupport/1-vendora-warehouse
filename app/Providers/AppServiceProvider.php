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
        \Illuminate\Support\Facades\View::composer('*', function ($view) {
            try {
                $license = \App\Models\AppLicense::latest()->first();
                $appName = ($license && !empty($license->custom_app_name))
                    ? $license->custom_app_name
                    : config('app.name', 'Vendora Shopee Management');
                $appLogo = ($license && !empty($license->custom_logo_url))
                    ? $license->custom_logo_url
                    : null;
                $appLicense = $license;
            } catch (\Throwable $e) {
                $appName = config('app.name', 'Vendora Shopee Management');
                $appLogo = null;
                $appLicense = null;
            }

            $view->with([
                'currentAppName' => $appName,
                'currentAppLogo' => $appLogo,
                'currentLicense' => $appLicense,
            ]);
        });
    }
}
