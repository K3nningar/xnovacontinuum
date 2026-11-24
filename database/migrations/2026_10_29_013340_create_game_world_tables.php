<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
            $table->string('name');
            $table->string('tag', 8);
            $table->timestamps();

            // Requis pour les FK composites (déclaré UNE SEULE fois)
            $table->unique(['id', 'universe_id']);
            $table->unique(['universe_id', 'tag']);
            $table->unique(['universe_id', 'name']);
        });

        // Colonnes + index uniques de users d'abord
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('universe_id')->nullable()->index();
            $table->unsignedBigInteger('alliance_id')->nullable()->index();
            $table->unique(['id', 'universe_id']); // requis pour planets et fleets
        });

        // Puis les clés étrangères
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('universe_id')
                ->references('id')->on('universes')->nullOnDelete();

            $table->foreign(['alliance_id', 'universe_id'], 'users_alliance_universe_foreign')
                ->references(['id', 'universe_id'])->on('alliances')->restrictOnDelete();
        });

        Schema::create('planets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('universe_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
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

            $table->foreign(['user_id', 'universe_id'])
                ->references(['id', 'universe_id'])->on('users')->cascadeOnDelete();
        });

        Schema::create('fleets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('universe_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('origin_planet_id')->nullable();
            $table->unsignedBigInteger('target_planet_id')->nullable();
            $table->string('mission', 24)->index();
            $table->json('ships');
            $table->string('status', 20)->default('outbound')->index();
            $table->timestamp('arrives_at')->index();
            $table->timestamp('returns_at')->nullable()->index();
            $table->timestamps();

            $table->index(['universe_id', 'user_id', 'status']);

            $table->foreign(['user_id', 'universe_id'])
                ->references(['id', 'universe_id'])->on('users')->cascadeOnDelete();
            $table->foreign(['origin_planet_id', 'universe_id'])
                ->references(['id', 'universe_id'])->on('planets')->restrictOnDelete();
            $table->foreign(['target_planet_id', 'universe_id'])
                ->references(['id', 'universe_id'])->on('planets')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Ordre inverse des dépendances
        Schema::dropIfExists('fleets');
        Schema::dropIfExists('planets');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_alliance_universe_foreign');
            $table->dropForeign(['universe_id']);
            $table->dropUnique(['id', 'universe_id']);
            $table->dropIndex(['alliance_id']);
            $table->dropIndex(['universe_id']);
            $table->dropColumn(['universe_id', 'alliance_id']);
        });

        Schema::dropIfExists('alliances');
        Schema::dropIfExists('universes');
    }
};