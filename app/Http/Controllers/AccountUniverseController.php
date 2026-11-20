<?php

namespace App\Http\Controllers;

use App\Models\Universe;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Response;

class AccountUniverseController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->user()->universe_id !== null) {
            $account = User::query()->whereNull('universe_id')
                ->where('commander_id', $request->user()->commander_id)->first();
            if ($account) {
                Auth::login($account, $request->session()->pull('auth.remember', false));
                $request->session()->regenerate();
                $request->session()->put('activeCommanderUserId', $account->id);
            }
        }

        $universes = Universe::query()->where('status', 'active')->orderBy('id')
            ->get(['id', 'name', 'galaxies', 'systems_per_galaxy']);

        if ($universes->isEmpty()) {
            return redirect()->route('dashboard');
        }

        $profiles = DB::table('commander_profiles')->join('users', 'users.id', '=', 'commander_profiles.profile_user_id')
            ->where('commander_profiles.commander_id', $request->user()->commander_id)
            ->whereIn('commander_profiles.universe_id', $universes->pluck('id'))
            ->get(['users.id', 'commander_profiles.universe_id', 'users.name']);
        $legacyProfiles = User::query()->where('commander_id', $request->user()->commander_id)
            ->whereIn('universe_id', $universes->pluck('id'))
            ->whereNotIn('id', $profiles->pluck('id'))->get(['id', 'universe_id', 'name']);
        $profiles = $profiles->concat($legacyProfiles);
        $profiledUniverseIds = $profiles->pluck('universe_id')->map(fn ($id): int => (int) $id)->all();

        return inertia('Auth/Universes', [
            'accountId' => $request->user()->id,
            'universes' => $universes->map(fn (Universe $universe): array => [
                ...$universe->only('id', 'name', 'galaxies', 'systems_per_galaxy'),
                'hasEmpire' => in_array((int) $universe->id, $profiledUniverseIds, true),
                'profileName' => $profiles->firstWhere('universe_id', $universe->id)?->name,
            ])->values(),
        ]);
    }

    public function select(Request $request): RedirectResponse
    {
        $attributes = $request->validate([
            'universe_id' => ['required', 'integer', Rule::exists('universes', 'id')->where('status', 'active')],
            'account_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('universe_id')],
        ]);
        $account = User::query()->whereKey($attributes['account_id'])
            ->whereNull('universe_id')
            ->where('commander_id', $request->user()->commander_id)
            ->firstOrFail();
        $profileId = DB::table('commander_profiles')
            ->where('commander_id', $account->commander_id)
            ->where('universe_id', $attributes['universe_id'])->value('profile_user_id');
        $profile = $profileId ? User::query()->findOrFail($profileId) : null;
        if (! $profile) {
            $legacyProfile = User::query()->where('commander_id', $account->commander_id)
                ->where('universe_id', $attributes['universe_id'])->first();
            if ($legacyProfile) {
                DB::table('commander_profiles')->insert([
                    'commander_id' => $account->commander_id,
                    'profile_user_id' => $legacyProfile->id,
                    'universe_id' => $legacyProfile->universe_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $profile = $legacyProfile;
            }
        }

        if ($profile) {
            Auth::login($profile, $request->session()->pull('auth.remember', false));
            $request->session()->regenerate();
            $request->session()->put('activeCommanderUserId', $account->id);
            $request->session()->forget(['onboardingUniverseId', 'onboardingCommanderId', 'onboardingCommanderUserId']);

            return redirect()->route('dashboard');
        }

        $request->session()->put('onboardingUniverseId', (int) $attributes['universe_id']);
        $request->session()->put('onboardingCommanderUserId', $account->id);
        $request->session()->put('activeCommanderUserId', $account->id);

        return redirect()->route('dashboard');
    }
}
