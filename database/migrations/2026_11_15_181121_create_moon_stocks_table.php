<?php

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
