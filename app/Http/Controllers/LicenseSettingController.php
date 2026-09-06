<?php

namespace App\Http\Controllers;

use App\Services\LicenseService;
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
        $machineId = $this->licenseService->getHardwareId();
        $serverUrl = $this->licenseService->getServerUrl();
        $defaultKey = config('services.license.key', null);
        $aiConfig = $this->licenseService->getAiConfig();

        return view('license.index', compact('license', 'machineId', 'serverUrl', 'defaultKey', 'aiConfig'));
    }

    public function sync(Request $request)
    {
        $validated = $request->validate([
            'license_key' => 'nullable|string|max:64',
            'server_url' => 'nullable|url|max:255',
        ]);

        $licenseKey = $validated['license_key'] ?? null;
        $serverUrl = $validated['server_url'] ?? null;

        if (empty($licenseKey)) {
            $result = $this->licenseService->checkAndRestoreFromHwid();
        } else {
            $result = $this->licenseService->verifyAndSync($licenseKey, $serverUrl);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        if ($result['success']) {
            return redirect()->route('license.index')->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    public function restoreHwid(Request $request)
    {
        $licenseKey = $request->input('license_key');
        $result = $this->licenseService->checkAndRestoreFromHwid($licenseKey);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        if ($result['success']) {
            return redirect()->route('license.index')->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }
}
