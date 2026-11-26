<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_module_settings', function (Blueprint $table): void {
            $table->string('key', 80)->primary();
            $table->boolean('enabled');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_module_settings');
    }
};
