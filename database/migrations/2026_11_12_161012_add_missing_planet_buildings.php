<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Defines database schema changes for 2026_11_12_161012_add_missing_planet_buildings.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planets', function (Blueprint $table) {
            $table->unsignedSmallInteger('terraformer_level')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('planets', function (Blueprint $table) {
            $table->dropColumn('terraformer_level');
        });
    }
};
