<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.5
 * Revision : 34ddc84
 * Description : Handles HTTP requests for UniverseController operations.
 */

namespace App\Http\Controllers;

use App\Models\Planet;
use App\Models\Universe;
use App\Services\GalaxyMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user, $attributes, $map): void {
            $user = $user->newQuery()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($user->universe_id !== null) {
                throw ValidationException::withMessages([
                    'universe' => 'Vous avez déjà rejoint un univers.',
                ]);
            }

            $universe = Universe::query()
                ->where('status', 'active')
                ->whereKey($attributes['universe_id'])
                ->lockForUpdate()
                ->first();

            if (! $universe) {
                throw ValidationException::withMessages([
                    'universe' => 'Aucun univers actif n’est disponible.',
                ]);
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

            $user->universe()->associate($universe);
            $user->save();

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
            $planet->user()->associate($user);
            $planet->universe()->associate($universe);
            $planet->save();
        });

        return to_route('dashboard')->with('success', 'Votre planète a été fondée. Bienvenue dans la galaxie.');
    }
}
