<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.5
 * Revision : 34ddc84
 * Description : Defines database schema changes for 2026_11_12_122216_add_research_levels_to_users_and_planets.
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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('spy_technology_level')->default(0);
            $table->unsignedSmallInteger('computer_technology_level')->default(0);
            $table->unsignedSmallInteger('military_technology_level')->default(0);
            $table->unsignedSmallInteger('defence_technology_level')->default(0);
            $table->unsignedSmallInteger('shield_technology_level')->default(0);
            $table->unsignedSmallInteger('hyperspace_technology_level')->default(0);
            $table->unsignedSmallInteger('combustion_technology_level')->default(0);
            $table->unsignedSmallInteger('impulse_technology_level')->default(0);
            $table->unsignedSmallInteger('hyperspace_drive_level')->default(0);
            $table->unsignedSmallInteger('laser_technology_level')->default(0);
            $table->unsignedSmallInteger('ion_technology_level')->default(0);
            $table->unsignedSmallInteger('plasma_technology_level')->default(0);
            $table->unsignedSmallInteger('intergalactic_research_level')->default(0);
            $table->unsignedSmallInteger('expedition_technology_level')->default(0);
            $table->unsignedSmallInteger('graviton_technology_level')->default(0);
        });

        Schema::table('planets', function (Blueprint $table) {
            $table->unsignedSmallInteger('laboratory_level')->default(0);
            $table->unsignedTinyInteger('laboratory_activity')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planets', function (Blueprint $table) {
            $table->dropColumn(['laboratory_level', 'laboratory_activity']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'spy_technology_level', 'computer_technology_level', 'military_technology_level',
                'defence_technology_level', 'shield_technology_level', 'hyperspace_technology_level',
                'combustion_technology_level', 'impulse_technology_level', 'hyperspace_drive_level',
                'laser_technology_level', 'ion_technology_level', 'plasma_technology_level',
                'intergalactic_research_level', 'expedition_technology_level', 'graviton_technology_level',
            ]);
        });
    }
};
