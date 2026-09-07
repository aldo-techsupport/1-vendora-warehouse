<?php

namespace App\Console\Commands;

use App\Services\ShopeeCloudSyncService;
use Illuminate\Console\Command;

class ShopeeSyncEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopee:sync-cloud-events {--server= : Custom Backend Cloud URL} {--license= : Custom License Key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch pending Shopee webhook events from Backend Cloud and update local warehouse stock';

    /**
     * Execute the console command.
     */
    public function handle(ShopeeCloudSyncService $syncService): int
    {
        $this->info('Memulai sinkronisasi pesanan Shopee dari Cloud Gateway...');

        $customServer = $this->option('server');
        $customLicense = $this->option('license');

        $result = $syncService->syncPendingEvents($customServer, $customLicense);

        if ($result['success']) {
            $this->info($result['message']);
            return Command::SUCCESS;
        }

        $this->error($result['message']);
        return Command::FAILURE;
    }
}
