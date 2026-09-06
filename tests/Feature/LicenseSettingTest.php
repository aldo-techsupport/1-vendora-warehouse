<?php

use App\Models\AppLicense;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->role = Role::firstOrCreate(
        ['slug' => 'super_admin'],
        [
            'name' => 'Super Administrator',
            'permissions' => ['*'],
        ]
    );

    $this->user = User::firstOrCreate(
        ['username' => 'test_admin'],
        [
            'name' => 'Test Admin',
            'email' => 'testadmin@vendora.id',
            'password' => Hash::make('password'),
            'role_id' => $this->role->id,
            'status' => 'active',
        ]
    );
});

test('license page renders correctly even when no license exists in database', function () {
    AppLicense::truncate();

    $response = $this->actingAs($this->user)
        ->get(route('license.index'));

    $response->assertStatus(200);
    $response->assertSee('Status Lisensi Aplikasi');
    $response->assertSee('BELUM AKTIF / PERLU SINKRONISASI');
    $response->assertSee('Vendora Shopee Management');
});

test('license page renders correctly with custom app branding when license exists', function () {
    AppLicense::truncate();
    AppLicense::create([
        'license_key' => 'MDN-CUSTOM-BRAND-2026',
        'client_name' => 'Toko Busana Modern',
        'client_email' => 'owner@busanamodern.com',
        'plan' => 'enterprise',
        'status' => 'active',
        'max_devices' => 10,
        'max_users' => 20,
        'custom_app_name' => 'Busana Modern Warehouse',
        'custom_logo_url' => 'https://example.com/logo.png',
        'is_lifetime' => true,
        'allowed_modules' => ['dashboard', 'orders', 'license'],
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('license.index'));

    $response->assertStatus(200);
    $response->assertSee('Busana Modern Warehouse');
    $response->assertSee('White-label Custom');
});

test('license page and layout render custom warehouse name and logo', function () {
    AppLicense::truncate();
    AppLicense::create([
        'license_key' => 'MDN-CUSTOM-WAREHOUSE-2026',
        'client_name' => 'Toko Sentosa Jaya',
        'client_email' => 'owner@sentosajaya.com',
        'plan' => 'enterprise',
        'status' => 'active',
        'max_devices' => 5,
        'max_users' => 10,
        'custom_app_name' => 'Sentosa Fashion POS',
        'custom_warehouse_name' => 'Gudang Utama Rungkut',
        'custom_logo_url' => 'https://example.com/sentosa_logo.png',
        'is_lifetime' => true,
        'allowed_modules' => ['dashboard', 'orders', 'license'],
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('license.index'));

    $response->assertStatus(200);
    $response->assertSee('Sentosa Fashion POS');
    $response->assertSee('Gudang Utama Rungkut');
    $response->assertSee('https://example.com/sentosa_logo.png');
});

