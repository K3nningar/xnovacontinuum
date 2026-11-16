<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BuildingController;
use App\Http\Controllers\CombatSimulatorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExplorationMissionController;
use App\Http\Controllers\GalaxyController;
use App\Http\Controllers\MoonController;
use App\Http\Controllers\MultiAccountDeclarationController;
use App\Http\Controllers\OfficerController;
use App\Http\Controllers\PlanetBuildingController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ResearchController;
use App\Http\Controllers\UnitProductionController;
use App\Http\Controllers\UniverseController;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\ProcessExplorationMissions;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => inertia('Welcome'))->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/connexion', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    Route::get('/inscription', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/inscription', [RegisteredUserController::class, 'store'])->middleware('throttle:register');
});

Route::middleware(['auth', EnsureAccountIsActive::class, ProcessExplorationMissions::class])->group(function () {
    Route::prefix('administration')->name('admin.')->middleware('admin:1')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::get('/elements', [AdminController::class, 'elements'])->middleware('admin:3')->name('elements');
        Route::post('/elements', [AdminController::class, 'storeElement'])->middleware(['admin:3', 'throttle:game-action'])->name('elements.store');
        Route::get('/ressources', [AdminController::class, 'resources'])->middleware('admin:2')->name('resources');
        Route::post('/ressources', [AdminController::class, 'storeResources'])->middleware(['admin:2', 'throttle:game-action'])->name('resources.store');
        Route::get('/roles', [AdminController::class, 'roles'])->middleware('admin:4')->name('roles');
        Route::post('/roles', [AdminController::class, 'updateRole'])->middleware(['admin:4', 'throttle:game-action'])->name('roles.update');
        Route::post('/suspensions', [AdminController::class, 'suspend'])->middleware('throttle:game-action')->name('suspend');
        Route::delete('/suspensions', [AdminController::class, 'unsuspend'])->middleware(['admin:2', 'throttle:game-action'])->name('unsuspend');
        Route::post('/annonces', [AdminController::class, 'storeAnnouncement'])->middleware('throttle:admin-announcement')->name('announcements.store');
        Route::delete('/annonces/{announcement}', [AdminController::class, 'revokeAnnouncement'])
            ->whereNumber('announcement')->middleware(['admin:3', 'throttle:game-action'])->name('announcements.revoke');
        Route::get('/declarations', [MultiAccountDeclarationController::class, 'administration'])->name('declarations');
    });
    Route::get('/annonces', AnnouncementController::class)->name('announcements.index');
    Route::get('/declaration-multicomptes', [MultiAccountDeclarationController::class, 'index'])->name('multi-accounts.index');
    Route::post('/declaration-multicomptes', [MultiAccountDeclarationController::class, 'store'])
        ->middleware('throttle:multi-account-declaration')->name('multi-accounts.store');
    Route::get('/commandement', DashboardController::class)->name('dashboard');
    Route::get('/galaxie', GalaxyController::class)->name('galaxy.index');
    Route::get('/planetes/{planet}/lunes/{orbit}', [MoonController::class, 'show'])->whereNumber('orbit')->name('moon.show');
    Route::post('/planetes/{planet}/lunes/{orbit}/transfert', [MoonController::class, 'store'])
        ->whereNumber('orbit')->middleware('throttle:game-action')->name('moon.transfer');
    Route::patch('/planetes/{planet}/nom', [DashboardController::class, 'rename'])
        ->middleware('throttle:game-action')->name('planet.rename');
    Route::get('/batiments', [BuildingController::class, 'index'])->name('buildings.index');
    Route::get('/recherches', [ResearchController::class, 'index'])->name('research.index');
    Route::get('/recherches/arbre', [ResearchController::class, 'tree'])->name('research.tree');
    Route::post('/planetes/{planet}/recherches', [ResearchController::class, 'store'])
        ->middleware('throttle:game-action')->name('planet.researches.store');
    Route::delete('/recherches/en-cours', [ResearchController::class, 'destroy'])
        ->middleware('throttle:game-action')->name('researches.destroy');
    Route::get('/flotte', [ProductionController::class, 'fleet'])->name('fleet.index');
    Route::post('/planetes/{planet}/missions', [ExplorationMissionController::class, 'store'])
        ->middleware('throttle:game-action')->name('planet.missions.store');
    Route::get('/simulateur-combat', [CombatSimulatorController::class, 'index'])->name('combat-simulator.index');
    Route::post('/simulateur-combat', [CombatSimulatorController::class, 'simulate'])
        ->middleware('throttle:game-action')->name('combat-simulator.simulate');
    Route::get('/chantier-spatial', [ProductionController::class, 'shipyard'])->name('shipyard.index');
    Route::get('/defenses', [ProductionController::class, 'defences'])->name('defences.index');
    Route::post('/planetes/{planet}/chantier/vaisseaux', [UnitProductionController::class, 'storeShip'])
        ->middleware('throttle:game-action')->name('planet.ships.store');
    Route::post('/planetes/{planet}/chantier/defenses', [UnitProductionController::class, 'storeDefence'])
        ->middleware('throttle:game-action')->name('planet.defences.store');
    Route::get('/officiers', [OfficerController::class, 'index'])->name('officers.index');
    Route::post('/officiers/recruter', [OfficerController::class, 'store'])
        ->middleware('throttle:game-action')->name('officers.store');
    Route::post('/univers/rejoindre', [UniverseController::class, 'join'])->middleware('throttle:universe-join')->name('universe.join');
    Route::post('/planetes/{planet}/batiments', [PlanetBuildingController::class, 'upgrade'])->middleware('throttle:game-action')->name('planet.buildings.upgrade');
    Route::post('/deconnexion', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
