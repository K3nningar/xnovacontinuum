<?php

declare(strict_types=1);

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Registers the application's web routes.
 */

use App\Http\Controllers\AccountUniverseController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BuildingController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\CombatSimulatorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExplorationMissionController;
use App\Http\Controllers\FactionAdminController;
use App\Http\Controllers\GalaxyController;
use App\Http\Controllers\MoonController;
use App\Http\Controllers\MultiAccountDeclarationController;
use App\Http\Controllers\OfficerController;
use App\Http\Controllers\PlanetBuildingController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResearchController;
use App\Http\Controllers\UnitProductionController;
use App\Http\Controllers\UniverseController;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\ProcessExplorationMissions;
use Illuminate\Support\Facades\Route;

/*
 * Public release pages and guest-only authentication are separated from the authenticated game pipeline.
 * Game routes first enforce active accounts and process due fleets; admin route groups add progressively
 * higher privilege thresholds, while mutating game operations carry named rate limits.
 */
Route::get('/', [ChangelogController::class, 'welcome'])->name('home');
Route::get('/changelog', [ChangelogController::class, 'index'])->name('changelog');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:register');
});

Route::middleware(['auth', EnsureAccountIsActive::class, ProcessExplorationMissions::class])->group(function () {
    Route::prefix('admin')->name('admin.')->middleware('admin:1')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::get('/changelog', [ChangelogController::class, 'administration'])->middleware('admin:3')->name('changelog');
        Route::patch('/changelog', [ChangelogController::class, 'update'])->middleware(['admin:3', 'throttle:game-action'])->name('changelog.update');
        Route::post('/changelog/entries', [ChangelogController::class, 'storeEntry'])->middleware(['admin:3', 'throttle:game-action'])->name('changelog.entries.store');
        Route::get('/elements', [AdminController::class, 'elements'])->middleware('admin:3')->name('elements');
        Route::post('/elements', [AdminController::class, 'storeElement'])->middleware(['admin:3', 'throttle:game-action'])->name('elements.store');
        Route::get('/resources', [AdminController::class, 'resources'])->middleware('admin:2')->name('resources');
        Route::post('/resources', [AdminController::class, 'storeResources'])->middleware(['admin:2', 'throttle:game-action'])->name('resources.store');
        Route::get('/universes', [AdminController::class, 'universes'])->middleware('admin:4')->name('universes');
        Route::get('/universes/{universe}/factions', [FactionAdminController::class, 'index'])->whereNumber('universe')->middleware('admin:4')->name('factions.index');
        Route::post('/universes/{universe}/factions', [FactionAdminController::class, 'store'])->whereNumber('universe')->middleware(['admin:4', 'throttle:game-action'])->name('factions.store');
        Route::patch('/universes/{universe}/factions/template', [FactionAdminController::class, 'updateTemplate'])->whereNumber('universe')->middleware(['admin:4', 'throttle:game-action'])->name('factions.template.update');
        Route::patch('/universes/{universe}/factions/{faction}', [FactionAdminController::class, 'update'])->whereNumber(['universe', 'faction'])->middleware(['admin:4', 'throttle:game-action'])->name('factions.update');
        Route::post('/universes/{universe}/diplomacy', [FactionAdminController::class, 'diplomacy'])->whereNumber('universe')->middleware(['admin:4', 'throttle:game-action'])->name('factions.diplomacy');
        Route::post('/universes', [AdminController::class, 'storeUniverse'])->middleware(['admin:4', 'throttle:game-action'])->name('universes.store');
        Route::post('/universes/{universe}/open', [AdminController::class, 'openUniverse'])->whereNumber('universe')
            ->middleware(['admin:4', 'throttle:game-action'])->name('universes.open');
        Route::get('/roles', [AdminController::class, 'roles'])->middleware('admin:3')->name('roles');
        Route::post('/roles', [AdminController::class, 'updateRole'])->middleware(['admin:3', 'throttle:game-action'])->name('roles.update');
        Route::post('/suspensions', [AdminController::class, 'suspend'])->middleware(['admin:3', 'throttle:game-action'])->name('suspend');
        Route::delete('/suspensions', [AdminController::class, 'unsuspend'])->middleware(['admin:3', 'throttle:game-action'])->name('unsuspend');
        Route::post('/announcements', [AdminController::class, 'storeAnnouncement'])->middleware(['admin:3', 'throttle:admin-announcement'])->name('announcements.store');
        Route::delete('/announcements/{announcement}', [AdminController::class, 'revokeAnnouncement'])
            ->whereNumber('announcement')->middleware(['admin:3', 'throttle:game-action'])->name('announcements.revoke');
        Route::get('/declarations', [MultiAccountDeclarationController::class, 'administration'])->middleware('admin:1')->name('declarations');
    });
    Route::get('/announcements', AnnouncementController::class)->name('announcements.index');
    Route::get('/multi-account-declarations', [MultiAccountDeclarationController::class, 'index'])->name('multi-accounts.index');
    Route::post('/multi-account-declarations', [MultiAccountDeclarationController::class, 'store'])
        ->middleware('throttle:multi-account-declaration')->name('multi-accounts.store');
    Route::get('/commandcenter', DashboardController::class)->name('dashboard');
    Route::get('/galaxy', GalaxyController::class)->name('galaxy.index');
    Route::get('/moon-buildings', [MoonController::class, 'buildings'])->name('moon.buildings');
    Route::get('/planets/{planet}/moons/{orbit}', [MoonController::class, 'show'])->whereNumber('orbit')->name('moon.show');
    Route::post('/planets/{planet}/moons/{orbit}/transfer', [MoonController::class, 'store'])
        ->whereNumber('orbit')->middleware('throttle:game-action')->name('moon.transfer');
    Route::patch('/planets/{planet}/moons/{orbit}/name', [MoonController::class, 'rename'])
        ->whereNumber('orbit')->middleware('throttle:game-action')->name('moon.rename');
    Route::post('/planets/{planet}/moons/{orbit}/buildings', [MoonController::class, 'upgrade'])
        ->whereNumber('orbit')->middleware('throttle:game-action')->name('moon.buildings.upgrade');
    Route::get('/planets/{planet}/moons/{orbit}/phalanx', [MoonController::class, 'phalanx'])
        ->whereNumber('orbit')->name('moon.phalanx');
    Route::post('/planets/{planet}/moons/{orbit}/jump', [MoonController::class, 'jump'])
        ->whereNumber('orbit')->middleware('throttle:game-action')->name('moon.jump');
    Route::patch('/planets/{planet}/name', [DashboardController::class, 'rename'])
        ->middleware('throttle:game-action')->name('planet.rename');
    Route::get('/buildings', [BuildingController::class, 'index'])->name('buildings.index');
    Route::get('/research', [ResearchController::class, 'index'])->name('research.index');
    Route::get('/research/tree', [ResearchController::class, 'tree'])->name('research.tree');
    Route::post('/planets/{planet}/research', [ResearchController::class, 'store'])
        ->middleware('throttle:game-action')->name('planet.researches.store');
    Route::delete('/research/in-progress', [ResearchController::class, 'destroy'])
        ->middleware('throttle:game-action')->name('researches.destroy');
    Route::get('/fleet', [ProductionController::class, 'fleet'])->name('fleet.index');
    Route::post('/planets/{planet}/missions', [ExplorationMissionController::class, 'store'])
        ->middleware('throttle:game-action')->name('planet.missions.store');
    Route::get('/combat-simulator', [CombatSimulatorController::class, 'index'])->name('combat-simulator.index');
    Route::post('/combat-simulator', [CombatSimulatorController::class, 'simulate'])
        ->middleware('throttle:game-action')->name('combat-simulator.simulate');
    Route::get('/shipyard', [ProductionController::class, 'shipyard'])->name('shipyard.index');
    Route::get('/defences', [ProductionController::class, 'defences'])->name('defences.index');
    Route::post('/planets/{planet}/shipyard/ships', [UnitProductionController::class, 'storeShip'])
        ->middleware('throttle:game-action')->name('planet.ships.store');
    Route::post('/planets/{planet}/shipyard/defences', [UnitProductionController::class, 'storeDefence'])
        ->middleware('throttle:game-action')->name('planet.defences.store');
    Route::get('/officers', [OfficerController::class, 'index'])->name('officers.index');
    Route::post('/officers/recruit', [OfficerController::class, 'store'])
        ->middleware('throttle:game-action')->name('officers.store');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:game-action')->name('profile.password');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->middleware('throttle:game-action')->name('profile.avatar');
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar'])->middleware('throttle:game-action')->name('profile.avatar.destroy');
    Route::post('/profile/two-factor/setup', [ProfileController::class, 'beginTwoFactorSetup'])->middleware('throttle:game-action')->name('profile.two-factor.setup');
    Route::post('/profile/two-factor', [ProfileController::class, 'enableTwoFactor'])->middleware('throttle:game-action')->name('profile.two-factor.enable');
    Route::delete('/profile/two-factor', [ProfileController::class, 'disableTwoFactor'])->middleware('throttle:game-action')->name('profile.two-factor');
    Route::get('/account/universes', [AccountUniverseController::class, 'index'])->name('account.universes');
    Route::post('/account/universes', [AccountUniverseController::class, 'select'])->middleware('throttle:account-universe-select')->name('account.universes.select');
    Route::post('/universe/join', [UniverseController::class, 'join'])->middleware('throttle:universe-join')->name('universe.join');
    Route::post('/planets/{planet}/buildings', [PlanetBuildingController::class, 'upgrade'])->middleware('throttle:game-action')->name('planet.buildings.upgrade');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
