<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.5
 * Revision : 34ddc84
 * Description : Defines database schema changes for 2026_11_15_142303_create_multi_account_declarations_table.
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
        Schema::create('multi_account_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('universe_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('declarant_id');
            // Composite universe foreign keys prevent a declaration from linking users across universe boundaries.
            $table->foreign(['declarant_id', 'universe_id'])
                ->references(['id', 'universe_id'])->on('users')->cascadeOnDelete();
            $table->string('reason', 500);
            $table->timestamps();
            $table->index(['declarant_id', 'id']);
            $table->unique(['id', 'universe_id']);
        });
        Schema::create('multi_account_declaration_players', function (Blueprint $table) {
            $table->unsignedBigInteger('declaration_id');
            $table->unsignedBigInteger('player_id');
            $table->unsignedBigInteger('universe_id');
            $table->foreign(['declaration_id', 'universe_id'], 'multi_players_declaration_universe_fk')
                ->references(['id', 'universe_id'])->on('multi_account_declarations')->cascadeOnDelete();
            $table->foreign(['player_id', 'universe_id'], 'multi_players_user_universe_fk')
                ->references(['id', 'universe_id'])->on('users')->cascadeOnDelete();
            $table->primary(['declaration_id', 'player_id']);
            $table->index('player_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('multi_account_declaration_players');
        Schema::dropIfExists('multi_account_declarations');
    }
};
