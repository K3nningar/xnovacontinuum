<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('universe_module_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('universe_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->boolean('enabled');
            $table->timestamps();
            $table->unique(['universe_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('universe_module_settings');
    }
};
