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
            if (! Schema::hasColumn('app_licenses', 'custom_warehouse_name')) {
                $table->string('custom_warehouse_name', 150)->nullable()->after('custom_app_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_licenses', function (Blueprint $table) {
            if (Schema::hasColumn('app_licenses', 'custom_warehouse_name')) {
                $table->dropColumn('custom_warehouse_name');
            }
        });
    }
};
