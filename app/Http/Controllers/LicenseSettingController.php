<?php

namespace App\Http\Controllers;

use App\Services\LicenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LicenseSettingController extends Controller
{
    public function __construct(
        protected LicenseService $licenseService
    ) {}

    public function index(): View
    {
        $license = $this->licenseService->getLocalLicense();
        $machineId = $this->licenseService->getMachineId();
        $serverUrl = $this->licenseService->getServerUrl();
        $defaultKey = config('services.license.key', 'MDN-MEDN-WARE-2026-PRO');
        $aiConfig = $this->licenseService->getAiConfig();

        return view('license.index', compact('license', 'machineId', 'serverUrl', 'defaultKey', 'aiConfig'));
    }

    public function sync(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'license_key' => 'required|string|max:64',
            'server_url' => 'nullable|url|max:255',
        ]);

        $licenseKey = $validated['license_key'];
        $serverUrl = $validated['server_url'] ?? null;
        $result = $this->licenseService->verifyAndSync($licenseKey, $serverUrl);

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }
}
