<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.5
 * Revision : 34ddc84
 * Description : Defines database schema changes for 2026_11_15_175202_add_exploration_mission_fields_to_fleets_table.
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
            // Nullable coordinate/cargo/outcome fields preserve compatibility with fleets predating exploration missions.
            $table->unsignedSmallInteger('target_galaxy')->nullable();
            $table->unsignedSmallInteger('target_system')->nullable();
            $table->unsignedSmallInteger('target_position')->nullable();
            $table->json('cargo')->nullable();
            $table->string('outcome', 32)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fleets', function (Blueprint $table) {
            $table->dropColumn(['target_galaxy', 'target_system', 'target_position', 'cargo', 'outcome']);
        });
    }
};
