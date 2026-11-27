<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Defines database schema changes for 2026_10_29_032119_create_planet_building_queues_table.
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
        Schema::create('planet_building_queues', function (Blueprint $table) {
			$table->id();
			$table->foreignId('universe_id')->constrained()->cascadeOnDelete();
			$table->foreignId('planet_id')->constrained()->cascadeOnDelete();
			$table->string('building_key', 24);
			$table->unsignedSmallInteger('building_code');
			$table->unsignedSmallInteger('target_level');
			$table->unsignedTinyInteger('position');
			$table->unsignedBigInteger('cost_metal');
			$table->unsignedBigInteger('cost_crystal');
			$table->unsignedBigInteger('cost_deuterium');

			// dateTime au lieu de timestamp : pas de défaut implicite
			$table->dateTime('starts_at');
			$table->dateTime('completes_at');

			$table->timestamps();

			// Index utile pour le traitement différé des files
			$table->index(['completes_at']);
			$table->unique(['planet_id', 'position']);
		});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planet_building_queues');
    }
};
