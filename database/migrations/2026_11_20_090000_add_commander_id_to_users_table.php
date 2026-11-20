<?php

/* Adds account grouping and an explicit per-universe profile map, backfilling current users without moving game state. */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'commander_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->uuid('commander_id')->nullable()->after('id');
                $table->string('email')->nullable()->change();
                $table->string('password')->nullable()->change();
            });
        }

        if (! Schema::hasTable('commander_profiles')) {
            Schema::create('commander_profiles', function (Blueprint $table) {
                $table->id();
                $table->uuid('commander_id');
                $table->unsignedBigInteger('profile_user_id')->unique();
                $table->unsignedBigInteger('universe_id');
                $table->foreign('profile_user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('universe_id')->references('id')->on('universes')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['commander_id', 'universe_id']);
            });
        }

        $lastUserId = (int) DB::table('users')->max('id');
        DB::table('users')->where('id', '<=', $lastUserId)->select('id')->orderBy('id')->chunkById(250, function ($rows): void {
            foreach ($rows as $row) {
                $profile = (array) DB::table('users')->where('id', $row->id)->first();
                $commanderId = $profile['commander_id'] ?: (string) Str::uuid();

                if ($profile['universe_id'] === null) {
                    DB::table('users')->where('id', $row->id)->update(['commander_id' => $commanderId]);

                    continue;
                }

                $accountId = DB::table('users')->where('commander_id', $commanderId)->whereNull('universe_id')->value('id');
                if (! $accountId) {
                    $account = $profile;
                    unset($account['id']);
                    $account['commander_id'] = $commanderId;
                    $account['universe_id'] = null;
                    $account['alliance_id'] = null;
                    $account['planetary_destroyer_losses'] = 0;
                    DB::table('users')->where('id', $row->id)->update([
                        'commander_id' => $commanderId,
                        'email' => null,
                        'email_verified_at' => null,
                        'admin_level' => 0,
                        'suspended_until' => null,
                        'suspension_reason' => null,
                    ]);
                    $accountId = DB::table('users')->insertGetId($account);
                }

                if (! DB::table('commander_profiles')->where('profile_user_id', $row->id)->exists()) {
                    DB::table('commander_profiles')->insert([
                        'commander_id' => $commanderId,
                        'profile_user_id' => $row->id,
                        'universe_id' => $profile['universe_id'],
                        'created_at' => $profile['created_at'],
                        'updated_at' => $profile['updated_at'],
                    ]);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('commander_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (DB::table('commander_profiles')->exists()) {
            throw new RuntimeException('Cannot roll back commander profiles while they contain empire ownership data.');
        }

        Schema::dropIfExists('commander_profiles');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('commander_id');
            $table->string('email')->unique()->nullable(false)->change();
        });
    }
};
