<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Defines database schema changes for 2026_11_12_133317_add_fleet_defence_and_officer_progression.
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
            $table->unsignedInteger('officer_points')->default(0);
            $table->unsignedSmallInteger('officer_geologist_level')->default(0);
            $table->unsignedSmallInteger('officer_admiral_level')->default(0);
            $table->unsignedSmallInteger('officer_engineer_level')->default(0);
            $table->unsignedSmallInteger('officer_technocrat_level')->default(0);
            $table->unsignedSmallInteger('officer_constructor_level')->default(0);
            $table->unsignedSmallInteger('officer_scientist_level')->default(0);
            $table->unsignedSmallInteger('officer_stockman_level')->default(0);
            $table->unsignedSmallInteger('officer_defender_level')->default(0);
            $table->unsignedSmallInteger('officer_bunker_level')->default(0);
            $table->unsignedSmallInteger('officer_spy_level')->default(0);
            $table->unsignedSmallInteger('officer_commander_level')->default(0);
            $table->unsignedSmallInteger('officer_destroyer_level')->default(0);
            $table->unsignedSmallInteger('officer_general_level')->default(0);
            $table->unsignedSmallInteger('officer_raider_level')->default(0);
            $table->unsignedSmallInteger('officer_emperor_level')->default(0);
        });

        Schema::table('planets', function (Blueprint $table) {
            $table->unsignedSmallInteger('hangar_level')->default(0);
            $table->unsignedSmallInteger('robot_factory_level')->default(0);
            $table->unsignedSmallInteger('nano_factory_level')->default(0);
            $table->unsignedSmallInteger('missile_silo_level')->default(0);
            foreach ([202, 203, 204, 205, 206, 207, 208, 209, 210, 211, 212, 213, 214, 215, 401, 402, 403, 404, 405, 406, 407, 408, 502, 503] as $code) {
                $table->unsignedBigInteger('unit_'.$code)->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planets', function (Blueprint $table) {
            $columns = ['hangar_level', 'robot_factory_level', 'nano_factory_level', 'missile_silo_level'];
            foreach ([202, 203, 204, 205, 206, 207, 208, 209, 210, 211, 212, 213, 214, 215, 401, 402, 403, 404, 405, 406, 407, 408, 502, 503] as $code) {
                $columns[] = 'unit_'.$code;
            }
            $table->dropColumn($columns);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'officer_points', 'officer_geologist_level', 'officer_admiral_level', 'officer_engineer_level',
                'officer_technocrat_level', 'officer_constructor_level', 'officer_scientist_level', 'officer_stockman_level',
                'officer_defender_level', 'officer_bunker_level', 'officer_spy_level', 'officer_commander_level',
                'officer_destroyer_level', 'officer_general_level', 'officer_raider_level', 'officer_emperor_level',
            ]);
        });
    }
};
