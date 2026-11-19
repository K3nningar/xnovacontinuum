<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planets', function (Blueprint $table): void {
            $table->unsignedSmallInteger('alliance_depot_level')->default(0);
            $table->timestamp('destroyed_at')->nullable()->index();
        });

        Schema::table('fleets', function (Blueprint $table): void {
            $table->string('target_type', 16)->nullable();
            $table->json('mission_report')->nullable();
        });

        Schema::create('orbital_debris', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('universe_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('galaxy');
            $table->unsignedSmallInteger('system');
            $table->unsignedSmallInteger('position');
            $table->unsignedBigInteger('metal')->default(0);
            $table->unsignedBigInteger('crystal')->default(0);
            $table->unique(['universe_id', 'galaxy', 'system', 'position']);
            $table->timestamps();
        });

        Schema::create('destroyed_moons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('universe_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('galaxy');
            $table->unsignedSmallInteger('system');
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('orbit');
            $table->unsignedSmallInteger('diameter');
            $table->unique(['universe_id', 'galaxy', 'system', 'position', 'orbit']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destroyed_moons');
        Schema::dropIfExists('orbital_debris');
        Schema::table('fleets', function (Blueprint $table): void {
            $table->dropColumn(['target_type', 'mission_report']);
        });
        Schema::table('planets', function (Blueprint $table): void {
            $table->dropIndex(['destroyed_at']);
            $table->dropColumn(['alliance_depot_level', 'destroyed_at']);
        });
    }
};
