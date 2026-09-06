<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;

class CheckLicenseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'license:check {--force : Force sync with backend cloud even if active locally}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Periksa dan sinkronisasi status lisensi otomatis berdasarkan Hardware ID (HWID)';

    /**
     * Execute the console command.
     */
    public function handle(LicenseService $licenseService): int
    {
        $this->info('=== Pemeriksaan Lisensi & Hardware ID (HWID) ===');
        $hwid = $licenseService->getHardwareId();
        $this->line("Hardware ID (HWID) Mesin ini : <fg=yellow>{$hwid}</>");
        $this->line("Server URL Lisensi            : <fg=cyan>{$licenseService->getServerUrl()}</>");

        $localLicense = $licenseService->getLocalLicense();

        if ($localLicense && $localLicense->isValid() && !$this->option('force')) {
            $this->info("\n✓ Lisensi lokal aktif ditemukan!");
            $this->table(
                ['Kunci Lisensi', 'Paket', 'Status', 'Branding Toko', 'Masa Berlaku'],
                [[
                    $localLicense->license_key,
                    $localLicense->plan,
                    $localLicense->status,
                    $localLicense->custom_app_name ?: 'Default',
                    $localLicense->is_lifetime ? 'Lifetime' : ($localLicense->expires_at?->format('d/m/Y') ?? '-'),
                ]]
            );
            return self::SUCCESS;
        }

        $this->line("\nMenghubungi Cloud Backend untuk memeriksa pendaftaran HWID...");
        $result = $licenseService->checkAndRestoreFromHwid();

        if ($result['success']) {
            $license = $result['license'];
            $this->info("\n✓ " . $result['message']);
            $this->table(
                ['Kunci Lisensi', 'Paket', 'Status', 'Branding Toko', 'Masa Berlaku'],
                [[
                    $license->license_key,
                    $license->plan,
                    $license->status,
                    $license->custom_app_name ?: 'Default',
                    $license->is_lifetime ? 'Lifetime' : ($license->expires_at?->format('d/m/Y') ?? '-'),
                ]]
            );
            return self::SUCCESS;
        }

        $this->warn("\n! " . $result['message']);
        $this->comment("Silakan buka antarmuka web di browser dan masukkan Kunci Lisensi Anda.");
        return self::FAILURE;
    }
}
