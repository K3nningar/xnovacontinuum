<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Defines database schema changes for 2026_11_12_144128_ensure_unit_and_officer_schema.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $userColumns = [
            'officer_points' => fn (Blueprint $table) => $table->unsignedInteger('officer_points')->default(0),
            'officer_geologist_level' => fn (Blueprint $table) => $table->unsignedSmallInteger('officer_geologist_level')->default(0),
            'officer_admiral_level' => fn (Blueprint $table) => $table->unsignedSmallInteger('officer_admiral_level')->default(0),
            'officer_engineer_level' => fn (Blueprint $table) => $table->unsignedSmallInteger('officer_engineer_level')->default(0),
            'officer_technocrat_level' => fn (Blueprint $table) => $table->unsignedSmallInteger('officer_technocrat_level')->default(0),
            'officer_constructor_level' => fn (Blueprint $table) => $table->unsignedSmallInteger('officer_constructor_level')->default(0),
            'officer_scientist_level' => fn (Blueprint $table) => $table->unsignedSmallInteger('officer_scientist_level')->default(0),
            'officer_stockman_level' => fn (Blueprint $table) => $table->unsignedSmallInteger('officer_stockman_level')->default(0),
            'officer_defender_level' => fn (Blueprint $table) => $table->unsignedSmallInteger('officer_defender_level')->default(0),
            'officer_bunker_level' => fn (Blueprint $table) => $table->unsignedSmallInteger('officer_bunker_level')->default(0),
            'officer_spy_level' => fn (Blueprint $table) => $table->unsignedSmallInteger('officer_spy_level')->default(0),
            'officer_commander_level' => fn (Blueprint $table) => $table->unsignedSmallInteger('officer_commander_level')->default(0),
            'officer_destroyer_level' => fn (Blueprint $table) => $table->unsignedSmallInteger('officer_destroyer_level')->default(0),
            'officer_general_level' => fn (Blueprint $table) => $table->unsignedSmallInteger('officer_general_level')->default(0),
            'officer_raider_level' => fn (Blueprint $table) => $table->unsignedSmallInteger('officer_raider_level')->default(0),
            'officer_emperor_level' => fn (Blueprint $table) => $table->unsignedSmallInteger('officer_emperor_level')->default(0),
        ];
        // Missing-column guards make this repair migration safe across partially upgraded installations.
        foreach ($userColumns as $column => $definition) {
            if (! Schema::hasColumn('users', $column)) {
                Schema::table('users', $definition);
            }
        }

        $planetColumns = [
            'hangar_level' => fn (Blueprint $table) => $table->unsignedSmallInteger('hangar_level')->default(0),
            'robot_factory_level' => fn (Blueprint $table) => $table->unsignedSmallInteger('robot_factory_level')->default(0),
            'nano_factory_level' => fn (Blueprint $table) => $table->unsignedSmallInteger('nano_factory_level')->default(0),
            'missile_silo_level' => fn (Blueprint $table) => $table->unsignedSmallInteger('missile_silo_level')->default(0),
        ];
        foreach ([202, 203, 204, 205, 206, 207, 208, 209, 210, 211, 212, 213, 214, 215, 401, 402, 403, 404, 405, 406, 407, 408, 502, 503] as $code) {
            $planetColumns['unit_'.$code] = fn (Blueprint $table) => $table->unsignedBigInteger('unit_'.$code)->default(0);
        }
        foreach ($planetColumns as $column => $definition) {
            if (! Schema::hasColumn('planets', $column)) {
                Schema::table('planets', $definition);
            }
        }

        if (! Schema::hasColumn('planet_unit_queues', 'universe_id')) {
            Schema::table('planet_unit_queues', function (Blueprint $table) {
                $table->foreignId('universe_id')->nullable()->constrained()->cascadeOnDelete();
            });
        }
        if (! Schema::hasColumn('planet_unit_queues', 'planet_id')) {
            Schema::table('planet_unit_queues', function (Blueprint $table) {
                $table->foreignId('planet_id')->nullable()->constrained()->cascadeOnDelete();
            });
        }

        $queueColumns = [
            'unit_code' => fn (Blueprint $table) => $table->unsignedSmallInteger('unit_code')->default(0),
            'unit_type' => fn (Blueprint $table) => $table->string('unit_type', 12)->default('ships'),
            'quantity' => fn (Blueprint $table) => $table->unsignedInteger('quantity')->default(0),
            'quantity_completed' => fn (Blueprint $table) => $table->unsignedInteger('quantity_completed')->default(0),
            'position' => fn (Blueprint $table) => $table->unsignedTinyInteger('position')->default(1),
            'cost_metal' => fn (Blueprint $table) => $table->unsignedBigInteger('cost_metal')->default(0),
            'cost_crystal' => fn (Blueprint $table) => $table->unsignedBigInteger('cost_crystal')->default(0),
            'cost_deuterium' => fn (Blueprint $table) => $table->unsignedBigInteger('cost_deuterium')->default(0),
            'starts_at' => fn (Blueprint $table) => $table->timestamp('starts_at')->nullable(),
            'completes_at' => fn (Blueprint $table) => $table->timestamp('completes_at')->nullable(),
        ];
        foreach ($queueColumns as $column => $definition) {
            if (! Schema::hasColumn('planet_unit_queues', $column)) {
                Schema::table('planet_unit_queues', $definition);
            }
        }

    }

    public function down(): void
    {
        // Deliberately left intact so this recovery migration does not remove columns used by production data.
    }
};
