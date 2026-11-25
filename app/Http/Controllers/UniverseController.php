<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Handles HTTP requests for UniverseController operations.
 */

namespace App\Http\Controllers;

use App\Models\Faction;
use App\Models\Planet;
use App\Models\Universe;
use App\Models\User;
use App\Services\GalaxyMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UniverseController extends Controller
{
    /**
     * Atomically joins an active universe and creates a starting planet in a randomly chosen unoccupied system.
     *
     * Locks the user and selected universe to prevent duplicate joins and concurrent allocation races. System
     * selection walks every bounded slot from a random offset; a full/invalid universe leaves the user unchanged.
     */
    public function join(Request $request, GalaxyMap $map): RedirectResponse
    {
        $attributes = $request->validate([
            'universe_id' => ['required', 'integer', Rule::exists('universes', 'id')->where('status', 'active')],
            'name' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[\pL\pN][\pL\pN .\-\']*$/u'],
            'faction_key' => ['sometimes', 'required', 'string', 'max:40'],
        ]);

        $account = User::query()->where('commander_id', $request->user()->commander_id)
            ->whereNull('universe_id')->first();
        if (! $account) {
            throw ValidationException::withMessages(['universe' => 'Compte commandant introuvable.']);
        }
        $universeId = (int) ($request->session()->get('onboardingUniverseId') ?? $attributes['universe_id']);
        if ($universeId !== (int) $attributes['universe_id']) {
            throw ValidationException::withMessages(['universe_id' => 'L’univers sélectionné a changé. Recommencez la sélection.']);
        }
        $profile = null;

        DB::transaction(function () use ($account, $attributes, $map, $universeId, &$profile): void {
            $account = $account->newQuery()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();

            $universe = Universe::query()
                ->where('status', 'active')
                ->whereKey($universeId)
                ->lockForUpdate()
                ->first();

            if (! $universe) {
                throw ValidationException::withMessages([
                    'universe' => 'Aucun univers actif n’est disponible.',
                ]);
            }

            $factionKey = $attributes['faction_key'] ?? '0';
            if ($factionKey !== '0' && ! Faction::query()->where('universe_id', $universe->id)->where('key', $factionKey)->exists()) {
                throw ValidationException::withMessages(['faction_key' => 'Cette faction n’est pas disponible dans cet univers.']);
            }

            $occupiedSystems = Planet::query()
                ->where('universe_id', $universe->id)
                ->select(['galaxy', 'system'])
                ->distinct()
                ->get()
                ->mapWithKeys(fn (Planet $planet) => [$planet->galaxy.':'.$planet->system => true]);

            $galaxyCount = min(9, (int) $universe->galaxies);
            $systemsPerGalaxy = min(499, (int) $universe->systems_per_galaxy);
            $slotCount = $galaxyCount * $systemsPerGalaxy;

            if ($slotCount === 0) {
                throw ValidationException::withMessages([
                    'universe' => 'Cet univers ne dispose pas de systèmes valides.',
                ]);
            }

            $startOffset = random_int(0, $slotCount - 1);
            $galaxy = null;
            $system = null;

            for ($offset = 0; $offset < $slotCount; $offset++) {
                $slot = ($startOffset + $offset) % $slotCount;
                $candidateGalaxy = intdiv($slot, $systemsPerGalaxy) + 1;
                $candidateSystem = ($slot % $systemsPerGalaxy) + 1;

                if (! $occupiedSystems->has($candidateGalaxy.':'.$candidateSystem)) {
                    $galaxy = $candidateGalaxy;
                    $system = $candidateSystem;
                    break;
                }
            }

            if ($galaxy === null || $system === null) {
                throw ValidationException::withMessages([
                    'universe' => 'Cet univers ne dispose plus de systèmes libres.',
                ]);
            }

            if (DB::table('commander_profiles')->where('commander_id', $account->commander_id)->where('universe_id', $universe->id)->exists()) {
                throw ValidationException::withMessages(['universe' => 'Vous possédez déjà un empire dans cet univers.']);
            }

            $profile = new User;
            $profile->name = $account->name;
            $profile->email = null;
            $profile->password = Str::random(64);
            $profile->forceFill([
                'commander_id' => $account->commander_id,
                'universe_id' => $universe->id,
                'faction_key' => $factionKey,
            ])->save();
            DB::table('commander_profiles')->insert([
                'commander_id' => $account->commander_id,
                'profile_user_id' => $profile->id,
                'universe_id' => $universe->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $planet = new Planet(['name' => $attributes['name']]);
            $planet->galaxy = $galaxy;
            $planet->system = $system;
            $planet->position = 8;
            $concentrations = $map->planetConcentrations($universe, $galaxy, $system, 8);
            $planet->metal_concentration = $concentrations['metal'];
            $planet->crystal_concentration = $concentrations['crystal'];
            $planet->deuterium_concentration = $concentrations['deuterium'];
            $planet->metal = 500;
            $planet->crystal = 500;
            $planet->deuterium = 500;
            $planet->fields = 163;
            $planet->max_temperature = 40;
            $planet->user()->associate($profile);
            $planet->universe()->associate($universe);
            $planet->save();
        });

        Auth::login($profile, $request->session()->pull('auth.remember', false));
        $request->session()->regenerate();
        $request->session()->forget(['onboardingUniverseId', 'onboardingCommanderId', 'onboardingCommanderUserId']);

        return to_route('dashboard')->with('success', 'Votre planète a été fondée. Bienvenue dans la galaxie.');
    }
}
