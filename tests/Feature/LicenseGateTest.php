<?php

use App\Models\AppLicense;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $role = Role::firstOrCreate(
        ['slug' => 'super_admin'],
        ['name' => 'Super Administrator', 'permissions' => ['*']]
    );

    $this->user = User::create([
        'name' => 'Admin Test',
        'username' => 'admintest',
        'email' => 'admin@test.com',
        'password' => Hash::make('password'),
        'role_id' => $role->id,
        'status' => 'active',
    ]);
});

test('user cannot access dashboard when no license exists', function () {
    AppLicense::truncate();
    Cache::flush();

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertRedirect(route('license.index'));
    $response->assertSessionHas('warning');
});

test('user can access license settings page even without license', function () {
    AppLicense::truncate();
    Cache::flush();

    $response = $this->actingAs($this->user)->get(route('license.index'));

    $response->assertStatus(200);
    $response->assertSee('Aplikasi Belum Teraktivasi!');
    $response->assertSee('Sinkronisasi & Aktifkan Lisensi', false);
});

test('user cannot access dashboard when license is expired', function () {
    AppLicense::truncate();
    Cache::flush();

    AppLicense::create([
        'license_key' => 'TEST-EXPIRED-2026',
        'status' => 'expired',
        'expires_at' => now()->subDay(),
        'is_lifetime' => false,
        'last_synced_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertRedirect(route('license.index'));
    $response->assertSessionHas('error');
});

test('user cannot access dashboard when license is suspended or revoked', function () {
    AppLicense::truncate();
    Cache::flush();

    AppLicense::create([
        'license_key' => 'TEST-SUSPENDED-2026',
        'status' => 'suspended',
        'is_lifetime' => true,
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertRedirect(route('license.index'));
    $response->assertSessionHas('error');
});

test('offline grace period keeps system active when network fails', function () {
    AppLicense::truncate();
    Cache::flush();

    AppLicense::create([
        'license_key' => 'TEST-OFFLINE-GRACE-2026',
        'status' => 'active',
        'expires_at' => now()->addMonths(6),
        'is_lifetime' => false,
        'last_synced_at' => now()->subHours(12),
    ]);

    // Simulate network failure / offline / weak connection
    Http::fake(function () {
        throw new ConnectionException('Could not resolve host or connection timeout');
    });

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    // System must NOT die; it should succeed under offline grace period
    $response->assertStatus(200);
});

test('system immediately locks when cloud heartbeat returns revoked', function () {
    AppLicense::truncate();
    Cache::flush();

    $license = AppLicense::create([
        'license_key' => 'TEST-ONLINE-REVOKED-2026',
        'status' => 'active',
        'expires_at' => now()->addMonths(6),
        'is_lifetime' => false,
        'last_synced_at' => now()->subDay(),
    ]);

    // Cloud explicitly responds that the license has been revoked
    Http::fake([
        '*/license/heartbeat' => Http::response([
            'success' => false,
            'status' => 'revoked',
            'message' => 'Lisensi ini telah dicabut oleh administrator cloud.',
        ], 403),
        '*/license/verify' => Http::response([
            'success' => false,
            'status' => 'revoked',
            'message' => 'Lisensi ini telah dicabut oleh administrator cloud.',
        ], 403),
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    // Local status must be immediately updated to revoked
    expect($license->fresh()->status)->toBe('revoked');

    // Access must be blocked immediately
    $response->assertRedirect(route('license.index'));
    $response->assertSessionHas('error');
});
