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
        Schema::table('app_licenses', function (Blueprint $table) {
            $table->text('custom_logo_url')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_licenses', function (Blueprint $table) {
            $table->string('custom_logo_url', 500)->nullable()->change();
        });
    }
};
