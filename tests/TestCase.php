<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Baseline active license for feature testing unless explicitly truncated
        if (! \App\Models\AppLicense::exists()) {
            \App\Models\AppLicense::create([
                'license_key' => 'TEST-DEV-LICENSE-KEY',
                'status' => 'active',
                'is_lifetime' => true,
                'plan' => 'Enterprise',
            ]);
        }
    }
}

