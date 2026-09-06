<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('app_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('license_key', 64)->unique();
            $table->string('status', 32)->default('inactive');
            $table->string('plan', 32)->default('Trial');
            $table->string('client_name', 150)->nullable();
            $table->string('client_email', 150)->nullable();
            $table->string('custom_app_name', 150)->nullable();
            $table->string('custom_logo_url', 500)->nullable();
            $table->json('allowed_modules')->nullable();
            $table->integer('max_users')->default(1);
            $table->integer('max_devices')->default(1);
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_lifetime')->default(false);
            $table->dateTime('last_synced_at')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_licenses');
    }
};
