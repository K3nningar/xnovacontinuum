<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('admin_level', '>', 0)->orderBy('id')
            ->chunkById(250, function ($users): void {
                foreach ($users as $user) {
                    if ($user->universe_id === null) {
                        continue;
                    }

                    if ($user->admin_level >= 3 && $user->admin_level <= 4) {
                        DB::table('users')->where('commander_id', $user->commander_id)->whereNull('universe_id')
                            ->where('admin_level', '<', $user->admin_level)->update(['admin_level' => $user->admin_level]);
                    } elseif ($user->admin_level >= 1 && $user->admin_level <= 2) {
                        DB::table('universe_staff')->insertOrIgnore([
                            'commander_id' => $user->commander_id, 'universe_id' => $user->universe_id,
                            'level' => $user->admin_level, 'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }

                    DB::table('users')->where('id', $user->id)->update(['admin_level' => 0]);
                }
            });

        DB::table('users')->whereNull('universe_id')->whereBetween('admin_level', [1, 2])
            ->orderBy('id')->chunkById(250, function ($accounts): void {
                foreach ($accounts as $account) {
                    $profiles = DB::table('users')->where('commander_id', $account->commander_id)->whereNotNull('universe_id')->get(['universe_id']);
                    if ($profiles->count() === 1) {
                        DB::table('universe_staff')->insertOrIgnore([
                            'commander_id' => $account->commander_id, 'universe_id' => $profiles->first()->universe_id,
                            'level' => $account->admin_level, 'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                    DB::table('users')->where('id', $account->id)->update(['admin_level' => 0]);
                }
            });
    }

    public function down(): void
    {
        throw new RuntimeException('Cannot reverse role normalization without losing universe-specific permissions.');
    }
};
