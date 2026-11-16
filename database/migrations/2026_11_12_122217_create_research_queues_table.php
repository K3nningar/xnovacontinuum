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
        Schema::create('research_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('universe_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('planet_id');
            $table->foreign(['user_id', 'universe_id'])->references(['id', 'universe_id'])->on('users')->cascadeOnDelete();
            $table->foreign(['planet_id', 'universe_id'])->references(['id', 'universe_id'])->on('planets')->cascadeOnDelete();
            $table->string('technology_key', 32);
            $table->unsignedSmallInteger('technology_code');
            $table->unsignedSmallInteger('target_level');
            $table->unsignedBigInteger('cost_metal');
            $table->unsignedBigInteger('cost_crystal');
            $table->unsignedBigInteger('cost_deuterium');
            $table->timestamp('completes_at')->index();
            $table->timestamps();
            $table->unique('user_id');
            $table->index(['universe_id', 'completes_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('research_queues');
    }
};
