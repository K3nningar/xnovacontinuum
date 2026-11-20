<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Defines database schema changes for 2026_11_15_175203_create_asteroid_fields_table.
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
        Schema::create('asteroid_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('universe_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('galaxy');
            $table->unsignedSmallInteger('system');
            $table->unsignedSmallInteger('position');
            $table->unsignedBigInteger('metal');
            $table->unsignedBigInteger('crystal');
            $table->unsignedBigInteger('deuterium');
            // Persist depletion by coordinate so deterministic map generation cannot replenish recycled fields.
            $table->unique(['universe_id', 'galaxy', 'system', 'position']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asteroid_fields');
    }
};
