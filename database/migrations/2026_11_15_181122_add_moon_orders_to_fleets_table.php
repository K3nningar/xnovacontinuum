<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Defines database schema changes for 2026_11_15_181122_add_moon_orders_to_fleets_table.
 */

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
        Schema::table('fleets', function (Blueprint $table) {
            // Only moon-transfer fleets populate these fields; null keeps all existing fleet rows valid.
            $table->unsignedSmallInteger('moon_orbit')->nullable();
            $table->json('moon_order')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fleets', function (Blueprint $table) {
            $table->dropColumn(['moon_orbit', 'moon_order']);
        });
    }
};
