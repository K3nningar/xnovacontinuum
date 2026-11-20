<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('universe_system_names', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('universe_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('galaxy');
            $table->unsignedSmallInteger('system');
            $table->string('star_name', 80);
            $table->json('planet_names');
            $table->json('moon_names');
            $table->timestamps();
            $table->unique(['universe_id', 'galaxy', 'system']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('universe_system_names');
    }
};
