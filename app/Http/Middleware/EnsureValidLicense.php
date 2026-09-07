<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidLicense
{
    public function __construct(
        protected LicenseService $licenseService
    ) {}

    /**
     * Handle an incoming request.
     * Enforces active license requirement on all warehouse routes while exempting license setup routes.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Allowed routes that can be accessed without an active license
        if ($this->isExcludedRoute($request)) {
            return $next($request);
        }

        // 2. Check if local license exists
        $license = $this->licenseService->getLocalLicense();

        if (! $license) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'status' => 'unlicensed',
                    'message' => 'Aplikasi belum teraktivasi. Silakan masukkan lisensi Anda terlebih dahulu.',
                ], 403);
            }

            return redirect()->route('license.index')->with(
                'warning',
                'Aplikasi belum teraktivasi. Silakan masukkan kunci lisensi untuk mengaktifkan seluruh fitur gudang.'
            );
        }

        // 3. Periodic background heartbeat check (throttled 1x per hour, safe with offline grace period)
        $this->licenseService->checkHeartbeatThrottled();

        // 4. Verify validity
        $license->refresh();

        if (! $license->isValid()) {
            $reason = $license->getInvalidReason();

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'status' => $license->status,
                    'message' => $reason,
                ], 403);
            }

            return redirect()->route('license.index')->with('error', $reason);
        }

        return $next($request);
    }

    /**
     * Check whether the current request route is excluded from license enforcement.
     */
    protected function isExcludedRoute(Request $request): bool
    {
        // Exclude license management routes so user can activate or restore license
        if ($request->routeIs('license.*')) {
            return true;
        }

        // Exclude authentication and session routes
        if ($request->routeIs('login', 'login.post', 'logout')) {
            return true;
        }

        // Exclude public webhooks and health check
        if ($request->is('shopee/webhook', 'api/shopee/webhook', 'up')) {
            return true;
        }

        return false;
    }
}
