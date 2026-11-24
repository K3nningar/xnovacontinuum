<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Handles HTTP requests for AdminController operations.
 */

namespace App\Http\Controllers;

use App\Models\Fleet;
use App\Models\Planet;
use App\Models\Universe;
use App\Models\User;
use App\Services\AdminAccess;
use App\Services\CelestialNames;
use App\Services\PlanetEconomy;
use App\Services\ResearchService;
use App\Services\UnitProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class AdminController extends Controller
{
    /**
     * Injects the canonical game catalogs and economy service used by audited administrative adjustments.
     */
    public function __construct(
        private PlanetEconomy $economy,
        private ResearchService $research,
        private UnitProductionService $production,
    ) {}

    /**
     * Renders aggregate administration overview and player selection data.
     */
    public function index(Request $request): Response
    {
        return $this->page($request, 'overview');
    }

    /**
     * Renders the canonical building/research/unit/officer adjustment catalog.
     */
    public function elements(Request $request): Response
    {
        return $this->page($request, 'elements');
    }

    /**
     * Renders player and planet selection data for resource adjustments.
     */
    public function resources(Request $request): Response
    {
        return $this->page($request, 'resources');
    }

    /**
     * Renders all universes and their opening status for founder-level management.
     */
    public function universes(Request $request): Response
    {
        return $this->page($request, 'universes');
    }

    /**
     * Renders administrator role/suspension data and recent audit records.
     */
    public function roles(Request $request): Response
    {
        return $this->page($request, 'roles');
    }

    /**
     * Creates a new active universe using bounded game-world settings and records the operation in the admin audit log.
     */
    public function storeUniverse(Request $request, CelestialNames $celestialNames): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'slug' => ['required', 'string', 'alpha_dash:ascii', 'min:2', 'max:80', 'unique:universes,slug'],
            'galaxies' => ['required', 'integer', 'between:1,9'],
            'systems_per_galaxy' => ['required', 'integer', 'between:1,499'],
            'economy_speed' => ['required', 'numeric', 'between:0.1,100'],
            'fleet_speed' => ['required', 'numeric', 'between:0.1,100'],
        ]);

        DB::transaction(function () use ($request, $data, $celestialNames): void {
            $universe = Universe::query()->create([
                ...$data,
                'status' => 'active',
            ]);
            $celestialNames->preGenerate($universe);
            $this->audit($request, 'universe_created', null, null, [
                'universe_id' => $universe->id,
                'name' => $universe->name,
                'slug' => $universe->slug,
                'galaxies' => $universe->galaxies,
                'systems_per_galaxy' => $universe->systems_per_galaxy,
                'named_systems' => DB::table('universe_system_names')->where('universe_id', $universe->id)->count(),
                'economy_speed' => $universe->economy_speed,
                'fleet_speed' => $universe->fleet_speed,
            ]);
        }, 3);

        return redirect()->route('admin.universes')->with('success', 'Univers créé et ouvert.');
    }

    /**
     * Opens a closed universe so new players can join it; existing players and worlds remain unchanged.
     */
    public function openUniverse(Request $request, Universe $universe, CelestialNames $celestialNames): RedirectResponse
    {
        DB::transaction(function () use ($request, $universe, $celestialNames): void {
            $universe = Universe::query()->lockForUpdate()->findOrFail($universe->id);
            if ($universe->status !== 'closed') {
                throw ValidationException::withMessages(['universe' => 'Cet univers est déjà ouvert.']);
            }

            $universe->status = 'active';
            $universe->save();
            $celestialNames->preGenerate($universe);
            $this->audit($request, 'universe_opened', null, null, [
                'universe_id' => $universe->id,
                'name' => $universe->name,
                'slug' => $universe->slug,
            ]);
        }, 3);

        return redirect()->route('admin.universes')->with('success', 'Univers ouvert.');
    }

    /**
     * Adds a bounded amount of a catalog element to an eligible player's planet or empire state.
     *
     * The transaction locks the target and planet, rejects conflicting queues/requirements and field or missile
     * capacity overflow, then writes the adjustment and audit record together. Buildings and solar satellites
     * settle economy first so the resulting stock/field state is current.
     */
    public function storeElement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'player_id' => ['required', 'integer', 'min:1', 'exists:users,id'],
            'planet_id' => ['required', 'integer', 'min:1'],
            'category' => ['required', 'string', 'in:buildings,research,ships,defences,officers'],
            'key' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'integer', 'between:1,100000'],
        ]);
        $definitions = $this->elementDefinitions();
        $definition = $definitions[$data['category']][$data['key']] ?? null;
        if ($definition === null) {
            throw ValidationException::withMessages(['key' => 'Cet élément est inconnu.']);
        }

        DB::transaction(function () use ($request, $data, $definition): void {
            $target = User::query()->lockForUpdate()->findOrFail($data['player_id']);
            $this->ensureEligibleTarget($request, $target);
            $planet = Planet::query()->whereKey($data['planet_id'])
                ->whereIn('user_id', $this->profileIds($target))
                ->lockForUpdate()->firstOrFail();
            $amount = (int) $data['amount'];
            $category = $data['category'];
            $column = $definition['column'];
            $owner = in_array($category, ['research', 'officers'], true) ? $target : $planet;

            if ($category === 'buildings') {
                if ($planet->buildingQueue()->exists()) {
                    throw ValidationException::withMessages(['key' => 'La file de construction doit être vide.']);
                }
                $planet = $this->economy->materialize($planet);
                $owner = $planet;
                $maximum = $this->economy->maximumBuildingLevel();
            } elseif ($category === 'research') {
                if ($target->researchQueues()->exists()) {
                    throw ValidationException::withMessages(['key' => 'Une recherche est déjà en cours.']);
                }
                $maximum = 30;
            } elseif ($category === 'officers') {
                if (DB::table('planet_unit_queues')->whereIn('planet_id', $target->planets()->select('id'))
                    ->where('unit_type', 'officers')->where('unit_code', $definition['code'])->exists()) {
                    throw ValidationException::withMessages(['key' => 'Cet officier est déjà en cours de recrutement.']);
                }
                $maximum = $definition['max'];
            } else {
                $queued = DB::table('planet_unit_queues')->where('unit_code', $definition['code'])
                    ->where('unit_type', $category);
                if (! empty($definition['uniqueEmpire'])) {
                    $queued->whereIn('planet_id', $target->planets()->select('id'));
                    if ($target->planets()->where($column, '>', 0)->exists() || $target->fleets()->exists()) {
                        throw ValidationException::withMessages(['key' => 'Cette unité unique ne peut pas être ajoutée tant qu’elle existe ou qu’une flotte est présente.']);
                    }
                } else {
                    $queued->where('planet_id', $planet->id);
                }
                if ($queued->exists()) {
                    throw ValidationException::withMessages(['key' => 'Cet élément est déjà dans une file de production.']);
                }
                $unitCatalog = $this->production->catalog($planet, $target, $category)['units'];
                $entry = collect($unitCatalog)->firstWhere('code', $definition['code']);
                if (! $entry || ! $entry['requirementsMet']) {
                    throw ValidationException::withMessages(['key' => 'Les prérequis de cette unité ne sont pas remplis.']);
                }
                if ($category === 'defences' && in_array($definition['code'], [502, 503], true)) {
                    $usedCapacity = (int) $planet->unit_502 + 2 * (int) $planet->unit_503;
                    $pendingMissiles = DB::table('planet_unit_queues')
                        ->where('planet_id', $planet->id)->where('unit_type', 'defences')
                        ->whereIn('unit_code', [502, 503])->get(['unit_code', 'quantity', 'quantity_completed']);
                    foreach ($pendingMissiles as $pending) {
                        $usedCapacity += ((int) $pending->quantity - (int) $pending->quantity_completed)
                            * ((int) $pending->unit_code === 503 ? 2 : 1);
                    }
                    $requiredCapacity = $amount * ($definition['code'] === 503 ? 2 : 1);
                    if ($usedCapacity + $requiredCapacity > 10 * (int) $planet->missile_silo_level) {
                        throw ValidationException::withMessages(['amount' => 'La capacité du silo de missiles serait dépassée.']);
                    }
                }
                $maximum = ! empty($definition['unique']) ? 1 : 1_000_000_000;
            }

            if ($column === 'unit_212') {
                $planet = $this->economy->materialize($planet);
                $owner = $planet;
            }

            $before = (int) $owner->{$column};
            if ($amount > $maximum - $before) {
                throw ValidationException::withMessages(['amount' => 'La limite de cet élément serait dépassée.']);
            }
            if ($category === 'buildings') {
                $newFields = (int) $planet->fields + ($data['key'] === 'terraformer' ? 5 * $amount : 0);
                $usedFields = collect($this->economy->administrativeBuildings())
                    ->sum(fn ($building) => (int) $planet->{$building['level']});
                if ($newFields > 65535 || $usedFields + $amount > $newFields) {
                    throw ValidationException::withMessages(['amount' => 'La limite de cases de la planète serait dépassée.']);
                }
                $planet->fields = $newFields;
            }
            $owner->{$column} = $before + $amount;
            $owner->save();
            $this->audit($request, 'element_added', $target->id, $planet->id, [
                'category' => $category, 'key' => $data['key'], 'amount' => $amount,
                'before' => $before, 'after' => $before + $amount,
            ]);
        }, 3);

        return redirect()->route('admin.elements', ['player' => $data['player_id'], 'planet' => $data['planet_id']])
            ->with('success', 'Élément ajouté.');
    }

    /**
     * Adds at least one resource to an eligible player's planet without exceeding computed storage capacities.
     *
     * Production is materialized first; the resource change and before/added/after audit details commit together.
     */
    public function storeResources(Request $request, PlanetEconomy $economy): RedirectResponse
    {
        $data = $request->validate([
            'player_id' => ['required', 'integer', 'min:1', 'exists:users,id'],
            'planet_id' => ['required', 'integer', 'min:1'],
            'metal' => ['required', 'integer', 'between:0,1000000000'],
            'crystal' => ['required', 'integer', 'between:0,1000000000'],
            'deuterium' => ['required', 'integer', 'between:0,1000000000'],
        ]);
        if ((int) $data['metal'] + (int) $data['crystal'] + (int) $data['deuterium'] === 0) {
            throw ValidationException::withMessages(['metal' => 'Indiquez au moins une ressource à ajouter.']);
        }

        DB::transaction(function () use ($request, $data, $economy): void {
            $target = User::query()->lockForUpdate()->findOrFail($data['player_id']);
            $this->ensureEligibleTarget($request, $target);
            $planet = Planet::query()->whereKey($data['planet_id'])
                ->whereIn('user_id', $this->profileIds($target))
                ->lockForUpdate()->firstOrFail();
            $planet = $economy->materialize($planet);
            $before = $planet->only('metal', 'crystal', 'deuterium');
            $capacities = $economy->storageCapacities($planet);
            foreach (['metal', 'crystal', 'deuterium'] as $resource) {
                if ((int) $data[$resource] > 0 && (int) $planet->{$resource} + (int) $data[$resource] > $capacities[$resource]) {
                    throw ValidationException::withMessages([$resource => 'La capacité de stockage serait dépassée.']);
                }
                $planet->{$resource} += (int) $data[$resource];
            }
            $planet->save();
            $this->audit($request, 'resources_added', $target->id, $planet->id, [
                'before' => $before,
                'added' => array_map('intval', array_intersect_key($data, array_flip(['metal', 'crystal', 'deuterium']))),
                'after' => $planet->only('metal', 'crystal', 'deuterium'),
            ]);
        }, 3);

        return redirect()->route('admin.resources', ['player' => $data['player_id'], 'planet' => $data['planet_id']])
            ->with('success', 'Ressources ajoutées.');
    }

    /**
     * Assigns an admin level from 0 to 3, preserving the founder account and auditing the previous/new level.
     *
     * The target is locked and may not be the actor or a level-four founder.
     */
    public function updateRole(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'player_id' => ['required', 'integer', 'min:1', 'exists:users,id'],
            'admin_level' => ['required', 'integer', 'between:0,3'],
        ]);

        DB::transaction(function () use ($request, $data): void {
            $target = User::query()->lockForUpdate()->findOrFail($data['player_id']);
            if ($target->commander_id === $request->user()->commander_id || $target->admin_level >= 4) {
                throw ValidationException::withMessages(['player_id' => 'Le compte fondateur ne peut pas être modifié ici.']);
            }

            $profiles = User::query()->where('commander_id', $target->commander_id)->lockForUpdate()->get();
            $previousLevel = $target->admin_level;
            foreach ($profiles as $profile) {
                $profile->admin_level = (int) $data['admin_level'];
                $profile->save();
            }
            $this->audit($request, 'role_changed', $target->id, null, [
                'commander_id' => $target->commander_id,
                'before' => $previousLevel,
                'after' => (int) $data['admin_level'],
            ]);
        }, 3);

        return redirect()->route('admin.roles', ['player' => $data['player_id']])
            ->with('success', 'Niveau administratif mis à jour.');
    }

    /**
     * Suspends a lower-privilege, non-self target for one to 365 days and records the reason in the audit log.
     *
     * Already-active suspensions are rejected; target state and audit event are persisted atomically.
     */
    public function suspend(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'player_id' => ['required', 'integer', 'min:1', 'exists:users,id'],
            'days' => ['required', 'integer', 'between:1,365'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        DB::transaction(function () use ($request, $data): void {
            $target = User::query()->lockForUpdate()->findOrFail($data['player_id']);
            $this->ensureEligibleTarget($request, $target);
            if ($target->isSuspended()) {
                throw ValidationException::withMessages(['player_id' => 'Ce compte est déjà suspendu.']);
            }
            $until = now()->addDays((int) $data['days']);
            User::query()->where('commander_id', $target->commander_id)->lockForUpdate()->update([
                'suspended_until' => $until,
                'suspension_reason' => $data['reason'],
            ]);
            $this->audit($request, 'account_suspended', $target->id, null, [
                'until' => $until->toIso8601String(), 'reason' => $data['reason'],
            ]);
        }, 3);

        return redirect()->route('admin.index', ['player' => $data['player_id']])
            ->with('success', 'Compte suspendu.');
    }

    /**
     * Clears a lower-privilege target's suspension deadline/reason and audits the previous suspension details.
     *
     * The operation is rejected when the target is not suspended and is committed atomically with its audit row.
     */
    public function unsuspend(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'player_id' => ['required', 'integer', 'min:1', 'exists:users,id'],
        ]);
        DB::transaction(function () use ($request, $data): void {
            $target = User::query()->lockForUpdate()->findOrFail($data['player_id']);
            $this->ensureEligibleTarget($request, $target);
            if (! $target->suspended_until) {
                throw ValidationException::withMessages(['player_id' => 'Ce compte n’est pas suspendu.']);
            }
            $previous = ['until' => $target->suspended_until->toIso8601String(), 'reason' => $target->suspension_reason];
            User::query()->where('commander_id', $target->commander_id)->lockForUpdate()->update([
                'suspended_until' => null,
                'suspension_reason' => null,
            ]);
            $this->audit($request, 'account_unsuspended', $target->id, null, $previous);
        }, 3);

        return redirect()->route('admin.index', ['player' => $data['player_id']])
            ->with('success', 'Suspension levée.');
    }

    /**
     * Publishes an announcement to a snapshot of all current user ids and records its audit event atomically.
     */
    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($request, $data): void {
            $id = DB::table('admin_announcements')->insertGetId([
                'actor_id' => $request->user()->id,
                'body' => $data['body'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('admin_announcement_recipients')->insertUsing(
                ['announcement_id', 'user_id'],
                DB::table('users')->selectRaw('? as announcement_id, id', [$id]),
            );
            $this->audit($request, 'announcement_created', null, null, [
                'announcement_id' => $id,
                'body' => $data['body'],
            ]);
        }, 3);

        return redirect()->route('admin.index')->with('success', 'Annonce publiée.');
    }

    /**
     * Marks an existing announcement as revoked once, preserving its record and recipient history.
     *
     * The announcement row is locked; missing ids return 404 and repeated revocation is rejected. The revocation
     * timestamp and audit event commit together.
     */
    public function revokeAnnouncement(Request $request, int $announcement): RedirectResponse
    {
        DB::transaction(function () use ($request, $announcement): void {
            $message = DB::table('admin_announcements')->where('id', $announcement)->lockForUpdate()->first();
            abort_if(! $message, 404);
            if ($message->revoked_at !== null) {
                throw ValidationException::withMessages(['announcement' => 'Cette annonce a déjà été retirée.']);
            }
            DB::table('admin_announcements')->where('id', $announcement)
                ->update(['revoked_at' => now(), 'updated_at' => now()]);
            $this->audit($request, 'announcement_revoked', null, null, ['announcement_id' => $announcement]);
        }, 3);

        return redirect()->route('admin.index')->with('success', 'Annonce retirée.');
    }

    /**
     * Builds shared administration page props, limiting overview/audit/catalog queries to the active mode.
     *
     * A selected planet id is retained only when it belongs to the selected player; player results preserve
     * validated search and pagination query parameters.
     *
     * @param  Request  $request  Validated selection/search query context.
     * @param  'overview'|'elements'|'resources'|'roles'|'universes'  $mode  Page data mode.
     */
    private function page(Request $request, string $mode): Response
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'player' => ['nullable', 'integer', 'min:1'],
            'planet' => ['nullable', 'integer', 'min:1'],
        ]);

        $allowedUniverses = app(AdminAccess::class)->manageableUniverseIds($request->user(), 1);
        $accounts = User::query()->whereNull('universe_id')
            ->when($allowedUniverses !== null, fn ($query) => $query->whereIn('commander_id', DB::table('commander_profiles')->whereIn('universe_id', $allowedUniverses)->select('commander_id')))
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')->orderBy('id')->get();
        $accountsById = $accounts->keyBy('id');
        $profilesByAccount = DB::table('commander_profiles')->whereIn('commander_id', $accounts->pluck('commander_id'))
            ->get()->groupBy('commander_id');
        $legacyProfiles = User::query()->whereIn('commander_id', $accounts->pluck('commander_id'))
            ->whereNotNull('universe_id')->whereNotIn('id', $profilesByAccount->flatten()->pluck('profile_user_id'))
            ->get()->groupBy('commander_id');
        $profileIds = $profilesByAccount->flatten()->pluck('profile_user_id')->merge($legacyProfiles->flatten()->pluck('id'));
        if ($allowedUniverses !== null) {
            $profileIds = User::query()->whereIn('id', $profileIds)->whereIn('universe_id', $allowedUniverses)->pluck('id');
        }
        $profiles = User::query()->whereIn('id', $profileIds)
            ->with(['universe:id,name', 'planets' => fn ($query) => $query->select(['id', 'user_id', 'name', 'galaxy', 'system', 'position'])])
            ->get()->keyBy('id');
        $players = $accounts->map(function (User $account) use ($profiles, $profilesByAccount, $legacyProfiles, $allowedUniverses): User {
            $ownedProfiles = $profilesByAccount->get($account->commander_id, collect())
                ->map(fn ($mapping) => $profiles->get($mapping->profile_user_id));
            $ownedProfiles = $ownedProfiles->merge($legacyProfiles->get($account->commander_id, collect())
                ->map(fn (User $profile) => $profiles->get($profile->id)))->filter();
            $account->setAttribute('planets', $ownedProfiles->flatMap->planets
                ->filter(fn (Planet $planet) => $allowedUniverses === null || $allowedUniverses->contains((int) $planet->universe_id))->values());
            $account->setAttribute('profiles', $ownedProfiles->map(fn (User $profile) => [
                'id' => $profile->id, 'universe_id' => $profile->universe_id,
                'universe_name' => $profile->universe?->name, 'name' => $profile->name,
            ])->values());

            return $account;
        });
        $players = new LengthAwarePaginator(
            $players->forPage((int) request()->query('page', 1), 20)->values(),
            $players->count(), 20, (int) request()->query('page', 1),
            ['path' => request()->url(), 'query' => request()->query()],
        );
        $players->withQueryString();

        $selectedPlayer = isset($data['player'])
            ? $accountsById->get((int) $data['player']) ?? User::query()->whereNull('universe_id')
                ->when($allowedUniverses !== null, fn ($query) => $query->whereIn('commander_id', DB::table('commander_profiles')->whereIn('universe_id', $allowedUniverses)->select('commander_id')))
                ->findOrFail($data['player'])
            : null;
        if ($selectedPlayer && ! $selectedPlayer->hasAttribute('planets')) {
            $ownedProfiles = $profilesByAccount->get($selectedPlayer->commander_id, collect())
                ->map(fn ($mapping) => $profiles->get($mapping->profile_user_id));
            $ownedProfiles = $ownedProfiles->merge($legacyProfiles->get($selectedPlayer->commander_id, collect())
                ->map(fn (User $profile) => $profiles->get($profile->id)))->filter();
            $selectedPlayer->setAttribute('planets', $ownedProfiles->flatMap->planets
                ->filter(fn (Planet $planet) => $allowedUniverses === null || $allowedUniverses->contains((int) $planet->universe_id))->values());
            $selectedPlayer->setAttribute('profiles', $ownedProfiles->map(fn (User $profile) => [
                'id' => $profile->id, 'universe_id' => $profile->universe_id,
                'universe_name' => $profile->universe?->name, 'name' => $profile->name,
            ])->values());
        }
        $selectedPlanetId = isset($data['planet']) && $selectedPlayer?->planets->contains('id', (int) $data['planet'])
            ? (int) $data['planet']
            : null;

        return inertia('Admin/Index', [
            'mode' => $mode,
            'players' => $players,
            'selectedPlayer' => $selectedPlayer,
            'selectedPlanetId' => $selectedPlanetId,
            'overview' => $mode === 'overview' ? [
                'players' => User::query()->whereNull('universe_id')->count(),
                'planets' => Planet::query()->when($allowedUniverses !== null, fn ($query) => $query->whereIn('universe_id', $allowedUniverses))->count(),
                'universes' => Universe::query()->when($allowedUniverses !== null, fn ($query) => $query->whereIn('id', $allowedUniverses))->count(),
                'fleets' => Fleet::query()->where('status', 'outbound')->when($allowedUniverses !== null, fn ($query) => $query->whereIn('universe_id', $allowedUniverses))->count(),
            ] : null,
            'activeFleets' => $mode === 'overview' ? DB::table('fleets')
                ->join('users', 'users.id', '=', 'fleets.user_id')
                ->where('fleets.status', 'outbound')
                ->when($allowedUniverses !== null, fn ($query) => $query->whereIn('fleets.universe_id', $allowedUniverses))
                ->orderBy('fleets.arrives_at')->orderBy('fleets.id')
                ->limit(25)->get(['fleets.id', 'fleets.mission', 'fleets.arrives_at', 'users.name as player']) : [],
            'adminAnnouncements' => $mode === 'overview' ? DB::table('admin_announcements')
                ->orderByDesc('id')->paginate(10, ['id', 'body', 'created_at', 'revoked_at'], 'announcements_page')
                ->withQueryString() : null,
            'auditLogs' => $mode === 'roles' ? DB::table('admin_audit_logs')->orderByDesc('id')->limit(50)
                ->get(['id', 'actor_id', 'actor_level', 'target_user_id', 'planet_id', 'action', 'details', 'created_at']) : [],
            'universes' => $mode === 'universes' ? Universe::query()->orderBy('id')->get([
                'id', 'name', 'slug', 'status', 'galaxies', 'systems_per_galaxy', 'economy_speed', 'fleet_speed',
            ])->map(function (Universe $universe): array {
                $universe->named_systems = min(
                    (int) $universe->galaxies * (int) $universe->systems_per_galaxy,
                    DB::table('universe_system_names')->where('universe_id', $universe->id)->count(),
                );

                return $universe->toArray();
            })->values() : [],
            'catalog' => $mode === 'elements' ? collect($this->elementDefinitions())
                ->flatMap(fn ($items, $category) => collect($items)->map(fn ($item, $key) => [
                    'category' => $category, 'key' => (string) $key, 'label' => $item['label'],
                ])->values())->values() : [],
        ]);
    }

    /**
     * Combines canonical economy, research, unit, and officer metadata into the admin adjustment lookup.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function elementDefinitions(): array
    {
        $buildings = [];
        foreach ($this->economy->administrativeBuildings() as $key => $building) {
            $buildings[$key] = ['column' => $building['level'], 'label' => 'buildingCatalog.'.$key.'.name'];
        }
        $research = [];
        foreach ($this->research->administrativeTechnologies() as $key => $technology) {
            $research[$key] = ['column' => $technology['column'], 'label' => 'research.'.$key];
        }
        $units = [];
        $columns = $this->production->administrativeUnitColumns();
        foreach ($this->production->administrativeUnits() as $category => $definitions) {
            foreach ($definitions as $code => $unit) {
                $units[$category][(string) $code] = [
                    ...$unit, 'code' => $code, 'column' => $columns[$code], 'label' => $unit['name'],
                ];
            }
        }
        $officers = [];
        $columns = $this->production->administrativeOfficerColumns();
        foreach ($this->production->administrativeOfficers() as $code => $officer) {
            $officers[(string) $code] = [
                ...$officer, 'code' => $code, 'column' => $columns[$code], 'label' => $officer['name'],
            ];
        }

        return ['buildings' => $buildings, 'research' => $research, ...$units, 'officers' => $officers];
    }

    /**
     * Writes an immutable audit snapshot of the actor, action, target context, and action-specific details.
     *
     * @param  array<string, mixed>  $details
     */
    private function audit(Request $request, string $action, ?int $targetUserId, ?int $planetId, array $details): void
    {
        DB::table('admin_audit_logs')->insert([
            'actor_id' => $request->user()->id,
            'actor_level' => $request->user()->admin_level,
            'target_user_id' => $targetUserId,
            'planet_id' => $planetId,
            'action' => $action,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Enforces the hierarchy rule that administrators may modify only other users below their own level.
     *
     * Self-targeting and equal/higher-level targets raise a validation exception.
     */
    /**
     * Returns the game-profile ids linked to an account, including its legacy direct profile during migration rollout.
     *
     * @return array<int>
     */
    private function profileIds(User $account): array
    {
        $ids = DB::table('commander_profiles')->where('commander_id', $account->commander_id)->pluck('profile_user_id')->all();

        if ($account->universe_id !== null) {
            $ids[] = $account->id;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function ensureEligibleTarget(Request $request, User $target): void
    {
        if ($target->id === $request->user()->id || $target->commander_id === $request->user()->commander_id
            || $target->admin_level >= $request->user()->admin_level) {
            throw ValidationException::withMessages(['player_id' => 'Vous ne pouvez pas modifier votre propre compte ou un compte de niveau égal ou supérieur.']);
        }
    }
}
