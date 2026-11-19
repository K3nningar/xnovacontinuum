<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.5
 * Revision : 34ddc84
 * Description : Defines database schema changes for 2026_10_29_013340_create_game_world_tables.
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
        Schema::create('universes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->string('status', 20)->default('active')->index();
            $table->unsignedSmallInteger('galaxies')->default(9);
            $table->unsignedSmallInteger('systems_per_galaxy')->default(499);
            $table->decimal('economy_speed', 6, 2)->default(1);
            $table->decimal('fleet_speed', 6, 2)->default(1);
            $table->timestamps();
        });

        Schema::create('alliances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('universe_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('tag', 12);
            $table->text('description')->nullable();
            $table->foreignId('leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['universe_id', 'tag']);
            $table->unique(['universe_id', 'name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('universe_id')->nullable()->index();
            $table->unsignedBigInteger('alliance_id')->nullable()->index();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('universe_id')->references('id')->on('universes')->nullOnDelete();
            $table->foreign(['alliance_id', 'universe_id'])->references(['id', 'universe_id'])->on('alliances')->restrictOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['id', 'universe_id']);
        });

        Schema::table('alliances', function (Blueprint $table) {
            $table->unique(['id', 'universe_id']);
        });

        Schema::create('planets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('universe_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->foreign(['user_id', 'universe_id'])->references(['id', 'universe_id'])->on('users')->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('galaxy');
            $table->unsignedSmallInteger('system');
            $table->unsignedSmallInteger('position');
            $table->unsignedBigInteger('metal')->default(500);
            $table->unsignedBigInteger('crystal')->default(500);
            $table->unsignedBigInteger('deuterium')->default(0);
            $table->unsignedSmallInteger('fields')->default(163);
            $table->timestamps();
            $table->unique(['universe_id', 'galaxy', 'system', 'position']);
            $table->unique(['id', 'universe_id']);
            $table->index(['universe_id', 'user_id']);
        });

        Schema::create('fleets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('universe_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('origin_planet_id')->nullable();
            $table->unsignedBigInteger('target_planet_id')->nullable();
            $table->foreign(['user_id', 'universe_id'])->references(['id', 'universe_id'])->on('users')->cascadeOnDelete();
            $table->foreign(['origin_planet_id', 'universe_id'])->references(['id', 'universe_id'])->on('planets')->restrictOnDelete();
            $table->foreign(['target_planet_id', 'universe_id'])->references(['id', 'universe_id'])->on('planets')->restrictOnDelete();
            $table->string('mission', 24)->index();
            $table->json('ships');
            $table->string('status', 20)->default('outbound')->index();
            $table->timestamp('arrives_at')->index();
            $table->timestamp('returns_at')->nullable()->index();
            $table->timestamps();
            $table->index(['universe_id', 'user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fleets');
        Schema::dropIfExists('planets');

        Schema::table('alliances', function (Blueprint $table) {
            $table->dropUnique(['id', 'universe_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['alliance_id', 'universe_id']);
            $table->dropForeign(['universe_id']);
            $table->dropUnique(['id', 'universe_id']);
            $table->dropIndex(['alliance_id']);
            $table->dropIndex(['universe_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['universe_id', 'alliance_id']);
        });

        Schema::dropIfExists('alliances');
        Schema::dropIfExists('universes');
    }
};
