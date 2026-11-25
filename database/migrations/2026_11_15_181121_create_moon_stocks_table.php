<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Defines database schema changes for 2026_11_15_181121_create_moon_stocks_table.
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
        Schema::create('moon_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planet_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('orbit');
            $table->unsignedBigInteger('metal')->default(0);
            $table->unsignedBigInteger('crystal')->default(0);
            $table->unsignedBigInteger('deuterium')->default(0);
            // A natural moon's resource stock is uniquely identified by its owning planet and orbit.
            $table->unique(['planet_id', 'orbit']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moon_stocks');
    }
};
