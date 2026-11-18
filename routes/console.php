<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Registers the application's console routes.
 */

use App\Models\User;
use App\Services\ExplorationMissions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Fleet transitions are also run on a non-overlapping minute schedule; authenticated web requests
 * invoke the same processor so due missions are not dependent solely on scheduler cadence.
 */
Artisan::command('fleets:process', function (ExplorationMissions $missions): void {
    $missions->processDue();
})->purpose('Traiter les missions de flotte arrivées à échéance');

Schedule::command('fleets:process')->everyMinute()->withoutOverlapping();

/*
 * Bootstraps the one founder role only when none exists. The broad ordered-row lock serializes competing
 * bootstrap attempts, and the role change plus audit entry are committed together.
 */
Artisan::command('admin:founder {userId}', function (string $userId): int {
    if (! ctype_digit($userId) || (int) $userId < 1) {
        $this->error('L’identifiant du joueur doit être un entier positif.');

        return 1;
    }

    return DB::transaction(function () use ($userId): int {
        User::query()->orderBy('id')->lockForUpdate()->first();
        if (User::query()->where('admin_level', 4)->exists()) {
            $this->error('Un fondateur existe déjà.');

            return 1;
        }

        $user = User::query()->lockForUpdate()->find($userId);
        if (! $user) {
            $this->error('Joueur introuvable.');

            return 1;
        }

        $previousLevel = $user->admin_level;
        $user->admin_level = 4;
        $user->save();
        DB::table('admin_audit_logs')->insert([
            'actor_id' => $user->id,
            'actor_level' => 4,
            'target_user_id' => $user->id,
            'action' => 'founder_bootstrap',
            'details' => json_encode(['previous_level' => $previousLevel, 'source' => 'console'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->info("Fondateur attribué au joueur #{$user->id}.");

        return 0;
    });
})->purpose('Attribuer le premier compte fondateur depuis la console');
