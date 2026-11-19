<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.5
 * Revision : 34ddc84
 * Description : Defines database schema changes for 2026_10_29_031345_add_energy_buildings_and_activity_to_planets_table.
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
        Schema::table('planets', function (Blueprint $table) {
            $table->unsignedSmallInteger('solar_plant_level')->default(0);
            $table->unsignedSmallInteger('fusion_plant_level')->default(0);
            $table->unsignedSmallInteger('metal_storage_level')->default(0);
            $table->unsignedSmallInteger('crystal_storage_level')->default(0);
            $table->unsignedSmallInteger('deuterium_storage_level')->default(0);
            $table->smallInteger('max_temperature')->default(40);
            $table->unsignedTinyInteger('metal_mine_activity')->default(10);
            $table->unsignedTinyInteger('crystal_mine_activity')->default(10);
            $table->unsignedTinyInteger('deuterium_synthesizer_activity')->default(10);
            $table->unsignedTinyInteger('solar_plant_activity')->default(10);
            $table->unsignedTinyInteger('fusion_plant_activity')->default(10);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planets', function (Blueprint $table) {
            $table->dropColumn([
                'solar_plant_level',
                'fusion_plant_level',
                'metal_storage_level',
                'crystal_storage_level',
                'deuterium_storage_level',
                'max_temperature',
                'metal_mine_activity',
                'crystal_mine_activity',
                'deuterium_synthesizer_activity',
                'solar_plant_activity',
                'fusion_plant_activity',
            ]);
        });
    }
};
