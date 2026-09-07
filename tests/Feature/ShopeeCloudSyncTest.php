<?php

namespace Tests\Feature;

use App\Models\AppLicense;
use App\Models\Product;
use App\Models\Role;
use App\Models\ShopeeOrder;
use App\Models\ShopeeSetting;
use App\Models\StockMutation;
use App\Models\User;
use App\Services\ShopeeCloudSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopeeCloudSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_shopee_settings_page_displays_cloud_gateway_urls(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => 'superadmin'],
            ['name' => 'Super Administrator', 'permissions' => ['shopee_settings']]
        );

        $user = User::create([
            'name' => 'Admin Test',
            'username' => 'admintest',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('shopee.settings'));

        $response->assertStatus(200);
        $response->assertSee('Shopee Cloud Gateway Relay');
        $response->assertSee('/shopee/webhook');
        $response->assertSee('/shopee/callback');
        $response->assertSee('Tarik & Sinkron Pesanan Cloud Sekarang', false);
    }

    public function test_sync_cloud_events_deducts_stock_and_acknowledges(): void
    {
        AppLicense::create([
            'license_key' => 'TEST-CLOUD-SYNC-2026',
            'status' => 'active',
            'is_lifetime' => true,
        ]);

        ShopeeSetting::create([
            'partner_id' => 12345,
            'partner_key' => 'secret',
            'shop_id' => 998877,
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Baju Koko Pria Premium',
            'sku' => 'KOKO-001',
            'stock' => 15,
            'shopee_item_id' => 77665544,
        ]);

        // Fake responses for Cloud API
        Http::fake([
            '*/client/shopee/events*' => Http::response([
                'success' => true,
                'count' => 1,
                'events' => [
                    [
                        'id' => 101,
                        'shop_id' => 998877,
                        'code' => 3,
                        'order_sn' => '260907CLOUDORDER1',
                        'order_status' => 'READY_TO_SHIP',
                        'payload' => [
                            'data' => [
                                'order_sn' => '260907CLOUDORDER1',
                                'status' => 'READY_TO_SHIP',
                                'buyer_username' => 'buyer_cloud',
                                'total_amount' => 95000,
                                'items' => [
                                    [
                                        'item_sku' => 'KOKO-001',
                                        'qty' => 3,
                                        'item_name' => 'Baju Koko Pria Premium',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
            '*/client/shopee/events/101/ack' => Http::response([
                'success' => true,
                'message' => 'Event acknowledged',
            ], 200),
        ]);

        $syncService = app(ShopeeCloudSyncService::class);
        $result = $syncService->syncPendingEvents();

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['processed']);

        // Assert stock was deducted from 15 to 12
        $this->assertEquals(12, $product->fresh()->stock);

        // Assert stock mutation was created
        $this->assertDatabaseHas('stock_mutations', [
            'reference_no' => 'SHP-260907CLOUDORDER1',
            'product_id' => $product->id,
            'qty' => -3,
            'stock_before' => 15,
            'stock_after' => 12,
        ]);

        // Assert order was saved
        $this->assertDatabaseHas('shopee_orders', [
            'order_sn' => '260907CLOUDORDER1',
            'stock_deducted' => true,
        ]);
    }
}
