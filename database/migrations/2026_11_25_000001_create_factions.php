<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('factions')) {
            Schema::create('factions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('universe_id')->constrained()->cascadeOnDelete();
                $table->string('key', 40);
                $table->string('name', 80);
                $table->text('description')->nullable();
                $table->string('image', 255)->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->unique(['universe_id', 'key']);
            });
        }

        if (! Schema::hasTable('faction_diplomacy')) {
            Schema::create('faction_diplomacy', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('universe_id')->constrained()->cascadeOnDelete();
                $table->string('first_faction', 40);
                $table->string('second_faction', 40);
                $table->string('status', 10);
                $table->timestamps();
            });
        }

        if (! Schema::hasIndex('faction_diplomacy', ['universe_id', 'first_faction', 'second_faction'], 'unique')) {
            Schema::table('faction_diplomacy', function (Blueprint $table): void {
                $table->unique(['universe_id', 'first_faction', 'second_faction'], 'faction_diplomacy_pair_unique');
            });
        }

        if (! Schema::hasColumn('users', 'faction_key')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('faction_key', 40)->default('0');
            });
        }

        if (! Schema::hasIndex('users', ['universe_id', 'faction_key'])) {
            Schema::table('users', function (Blueprint $table): void {
                $table->index(['universe_id', 'faction_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['universe_id', 'faction_key']);
            $table->dropColumn('faction_key');
        });
        Schema::dropIfExists('faction_diplomacy');
        Schema::dropIfExists('factions');
    }
};
