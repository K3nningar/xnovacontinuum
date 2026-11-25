<?php

namespace App\Http\Controllers;

use App\Models\Faction;
use App\Models\Universe;
use App\Services\AdminAccess;
use App\Services\FactionDiplomacy;
use App\Services\PlanetEconomy;
use App\Services\UnitProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class FactionAdminController extends Controller
{
    public function index(Universe $universe, PlanetEconomy $economy, UnitProductionService $production): Response
    {
        $members = $universe->users()->selectRaw('faction_key, COUNT(*) as total')->groupBy('faction_key')->pluck('total', 'faction_key');
        $factions = $universe->factions()->get()->map(fn (Faction $faction): array => [
            ...$faction->only('id', 'key', 'name', 'description', 'image', 'settings'),
            'members' => (int) ($members[$faction->key] ?? 0),
        ]);
        if (! $factions->contains('key', '0')) {
            $factions->prepend(['key' => '0', 'name' => 'Faction 0', 'members' => (int) ($members['0'] ?? 0)]);
        }

        return inertia('Admin/Factions', [
            'universe' => $universe->only('id', 'name', 'status'),
            'factions' => $factions->sortBy(fn (array $faction): int => $faction['key'] === '0' ? 0 : 1)->values(),
            'relations' => DB::table('faction_diplomacy')->where('universe_id', $universe->id)->get(['first_faction', 'second_faction', 'status']),
            'catalog' => [
                'buildings' => collect($economy->administrativeBuildings())->map(fn (array $building, string $key): array => [
                    'key' => $key, 'code' => $building['code'], 'translationKey' => $key, 'name' => $building['label'],
                ])->values(),
                ...collect($production->administrativeUnits())->map(fn (array $units) => collect($units)
                    ->map(fn (array $unit, int $code): array => [
                        'key' => (string) $code, 'code' => $code, 'translationKey' => $unit['key'], 'name' => $unit['name'],
                    ])->values())->all(),
            ],
        ]);
    }

    public function store(Request $request, Universe $universe): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_-]*$/', Rule::unique('factions', 'key')->where('universe_id', $universe->id)],
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'string', 'max:255', 'regex:~^/(?!/)[A-Za-z0-9_./-]+$~'],
        ]);

        DB::transaction(function () use ($universe, $data, $request): void {
            $universe->factions()->create($data);
            $this->audit($request, $universe, 'faction_created', ['key' => $data['key'], 'name' => $data['name']]);
        });

        return back()->with('success', 'Faction créée.');
    }

    public function diplomacy(Request $request, Universe $universe, FactionDiplomacy $diplomacy): RedirectResponse
    {
        $data = $request->validate([
            'first' => ['required', 'string', 'max:40'],
            'second' => ['required', 'string', 'max:40', 'different:first'],
            'status' => ['required', Rule::in(['war', 'neutral', 'allied'])],
        ]);
        $keys = $universe->factions()->pluck('key')->push('0');
        if (! $keys->contains($data['first']) || ! $keys->contains($data['second'])) {
            throw ValidationException::withMessages(['first' => 'Faction inconnue dans cet univers.']);
        }
        [$first, $second] = $diplomacy->orderedKeys($data['first'], $data['second']);
        DB::transaction(function () use ($universe, $first, $second, $data, $request): void {
            DB::table('faction_diplomacy')->updateOrInsert(
                ['universe_id' => $universe->id, 'first_faction' => $first, 'second_faction' => $second],
                ['status' => $data['status'], 'updated_at' => now(), 'created_at' => now()],
            );
            $this->audit($request, $universe, 'faction_diplomacy_changed', ['first' => $first, 'second' => $second, 'status' => $data['status']]);
        });

        return back()->with('success', 'Diplomatie mise à jour.');
    }

    public function update(Request $request, Universe $universe, Faction $faction): RedirectResponse
    {
        abort_unless((int) $faction->universe_id === (int) $universe->id, 404);

        return $this->saveSettings($request, $universe, $faction);
    }

    public function updateTemplate(Request $request, Universe $universe): RedirectResponse
    {
        return $this->saveSettings($request, $universe);
    }

    private function saveSettings(Request $request, Universe $universe, ?Faction $faction = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'string', 'max:255', 'regex:~^/(?!/)[A-Za-z0-9_./-]+$~'],
            'settings' => ['required', 'json'],
        ]);
        $settings = json_decode($data['settings'], true);
        $rules = [
            'settings' => ['present', 'array:buildings,ships,defences,statistics'],
            'settings.statistics' => ['array:attack,shield,hull'],
            'settings.statistics.*' => ['numeric', 'between:0.1,10'],
        ];
        foreach (['buildings', 'ships', 'defences'] as $category) {
            $rules["settings.$category"] = ['array'];
            $rules["settings.$category.*"] = ['array:available,name,image'];
            $rules["settings.$category.*.available"] = ['boolean'];
            $rules["settings.$category.*.name"] = ['string', 'max:80'];
            $rules["settings.$category.*.image"] = ['string', 'max:255', 'regex:~^/(?!/)[A-Za-z0-9_./-]+$~'];
        }
        Validator::make(['settings' => $settings], $rules)->validate();
        foreach (['buildings', 'ships', 'defences'] as $category) {
            foreach ($settings[$category] ?? [] as $key => $element) {
                if (array_key_exists('available', $element)) {
                    $settings[$category][$key]['available'] = (bool) $element['available'];
                }
            }
        }
        DB::transaction(function () use ($faction, $data, $settings, $request, $universe): void {
            $faction ??= $universe->factions()->firstOrCreate(['key' => '0'], ['name' => 'Faction 0']);
            $faction->update([...$data, 'settings' => $settings]);
            $this->audit($request, $universe, 'faction_updated', ['key' => $faction->key, 'name' => $data['name'], 'settings' => $settings]);
        });

        return back()->with('success', 'Faction mise à jour.');
    }

    private function audit(Request $request, Universe $universe, string $action, array $details): void
    {
        DB::table('admin_audit_logs')->insert([
            'actor_id' => $request->user()->id,
            'actor_level' => app(AdminAccess::class)->levelFor($request->user(), $universe->id),
            'universe_id' => $universe->id,
            'action' => $action,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
