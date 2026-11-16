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
            $table->timestamp('starts_at');
            $table->timestamp('completes_at')->index();
            $table->timestamps();
            $table->index(['planet_id', 'position']);
            $table->unique(['planet_id', 'building_key', 'target_level']);
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
