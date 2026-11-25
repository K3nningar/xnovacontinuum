<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FactionMigrationTest extends TestCase
{
    public function test_migration_recovers_partial_schema_without_losing_data(): void
    {
        $previousConnection = DB::getDefaultConnection();
        config(['database.connections.faction_migration_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('faction_migration_test');
        Schema::clearResolvedInstance('db.schema');

        try {
            Schema::create('universes', fn (Blueprint $table) => $table->id());
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('universe_id')->nullable();
            });
            $migration = require database_path('migrations/2026_11_25_000001_create_factions.php');
            $migration->up();

            DB::table('universes')->insert(['id' => 1]);
            DB::table('factions')->insert(['universe_id' => 1, 'key' => 'red', 'name' => 'Rouges']);
            DB::table('faction_diplomacy')->insert(['universe_id' => 1, 'first_faction' => 'blue', 'second_faction' => 'red', 'status' => 'neutral']);
            Schema::table('faction_diplomacy', fn (Blueprint $table) => $table->dropUnique('faction_diplomacy_pair_unique'));
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(['universe_id', 'faction_key']);
                $table->dropColumn('faction_key');
            });

            $migration->up();
            $migration->up();

            $this->assertTrue(Schema::hasColumn('users', 'faction_key'));
            $this->assertTrue(Schema::hasIndex('users', ['universe_id', 'faction_key']));
            $this->assertTrue(Schema::hasIndex('faction_diplomacy', ['universe_id', 'first_faction', 'second_faction'], 'unique'));
            $this->assertDatabaseHas('factions', ['key' => 'red', 'name' => 'Rouges']);
            $this->assertDatabaseHas('faction_diplomacy', ['first_faction' => 'blue', 'second_faction' => 'red', 'status' => 'neutral']);
        } finally {
            DB::setDefaultConnection($previousConnection);
            Schema::clearResolvedInstance('db.schema');
            DB::purge('faction_migration_test');
        }
    }
}
