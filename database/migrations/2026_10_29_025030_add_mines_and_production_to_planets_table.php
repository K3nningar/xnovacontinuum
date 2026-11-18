<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Defines database schema changes for 2026_10_29_025030_add_mines_and_production_to_planets_table.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('planets', function (Blueprint $table) {
            $table->unsignedSmallInteger('metal_mine_level')->default(0);
            $table->unsignedSmallInteger('crystal_mine_level')->default(0);
            $table->unsignedSmallInteger('deuterium_synthesizer_level')->default(0);
            $table->unsignedSmallInteger('metal_production_remainder')->default(0);
            $table->unsignedSmallInteger('crystal_production_remainder')->default(0);
            $table->unsignedSmallInteger('deuterium_production_remainder')->default(0);
            $table->timestamp('resources_updated_at')->nullable()->index();
        });

        DB::table('planets')->update(['resources_updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planets', function (Blueprint $table) {
            $table->dropIndex(['resources_updated_at']);
            $table->dropColumn([
                'metal_mine_level',
                'crystal_mine_level',
                'deuterium_synthesizer_level',
                'metal_production_remainder',
                'crystal_production_remainder',
                'deuterium_production_remainder',
                'resources_updated_at',
            ]);
        });
    }
};
