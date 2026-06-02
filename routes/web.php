<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\GridController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QoLController;
use App\Http\Controllers\FunctionController;
use App\Http\Controllers\EffectsController;
use App\Http\Controllers\FunctionManagementController;
use App\Http\Controllers\ConditionsController;
use App\Http\Controllers\UndoController;
use App\Policies\PagePolicy;
use App\Http\Controllers\SimulationEventController;

// Publieke route
Route::get('/', function () {
    return view('welcome');
});

// Alle routes hierbinnen vereisen dat de gebruiker is ingelogd
Route::middleware('auth')->group(function () {

    // Dashboard & Profiel routes
    Route::get('/dashboard', function () { return view('dashboard'); })->middleware(['verified', 'can:CanViewDashboard,' . PagePolicy::class])->name('dashboard');

    Route::middleware('can:CanViewProfile,' . PagePolicy::class)->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    // === GRID & KWALITEIT VAN DE LEFOMGEVING (QoL) ===
    Route::middleware('can:CanViewGridPage,' . PagePolicy::class)->group(function () {
        Route::get('/grid', [GridController::class, 'index'])->name('grid');
        Route::get('/qol/details', [QoLController::class, 'details'])->name('qol.details');
        Route::get('/qol/cell/{row}/{col}', [QoLController::class, 'cellHoverDetails']);
        
        // Functies plaatsen/verwijderen op het grid (City Planner)
        Route::post('/grid/update', [GridController::class, 'update'])->middleware('can:CanPlaceFunctions,' . PagePolicy::class);
        Route::post('/undo', [UndoController::class, 'undo'])->middleware('can:CanPlaceFunctions,' . PagePolicy::class);
        Route::delete('/grid/cell/{cell}/function', [GridController::class, 'removeFunction'])->middleware('can:CanPlaceFunctions,' . PagePolicy::class);
    });

    // === FUNCTIONS / FUNCTIEBEHEER ===
    // Pagina bekijken (City planner & Administrator)
    Route::middleware('can:CanViewFunctionPage,' . PagePolicy::class)->group(function () {
        Route::get('/functions', [FunctionManagementController::class, 'index'])->name('functions.index');
        Route::get('/library', [FunctionController::class, 'index'])->name('library');
        Route::get('/functions/manage', function () { return view('functions.manage'); })->name('functions.manage');
    });

    // Functies aanmaken, aanpassen en verwijderen (Alleen City Planner)
    Route::middleware('can:CanPlaceFunctions,' . PagePolicy::class)->group(function () {
        Route::get('/functions/create', [FunctionManagementController::class, 'create'])->name('functions.create');
        Route::post('/functions', [FunctionManagementController::class, 'store'])->name('functions.store');
        Route::get('/functions/{function}/edit', [FunctionManagementController::class, 'edit'])->name('functions.edit');
        Route::put('/functions/{function}', [FunctionManagementController::class, 'update'])->name('functions.update');
        Route::delete('/functions/{function}', [FunctionManagementController::class, 'destroy'])->name('functions.destroy');
    });

    // === EFFECTS ===
    Route::middleware('can:CanViewEffectsPage,' . PagePolicy::class)->group(function () {
        Route::get('/effects', [EffectsController::class, 'index'])->name('effects.index');
        // Updaten van effecten mag alleen de Administrator (CanChangeQOLEffect)
        Route::get('/functions/create', [FunctionManagementController::class, 'create'])->middleware('can:CanChangeQOLEffect,' . PagePolicy::class)->name('functions.create');
        Route::post('/functions', [FunctionManagementController::class, 'store'])->name('functions.store');
        Route::get('/functions/{function}/edit', [FunctionManagementController::class, 'edit'])->name('functions.edit');
        Route::delete('/functions/{function}', [FunctionManagementController::class, 'destroy'])->name('functions.destroy');

        Route::put('/functions/{function}', [FunctionManagementController::class, 'update'])->middleware('can:CanChangeQOLEffect,' . PagePolicy::class)->name('functions.update');
        Route::post('/effects/update', [EffectsController::class, 'update'])->middleware('can:CanChangeQOLEffect,' . PagePolicy::class)->name('effects.update');
    });

    // === CONDITIONS (Resource Route) ===
    // Bekijken mag via CanViewConditionsPage
    Route::get('/conditions', [ConditionsController::class, 'index'])
        ->middleware('can:CanViewConditionsPage,' . PagePolicy::class)
        ->name('conditions.index');

    // Aanmaken/wijzigen/verwijderen mag alleen de Administrator
    Route::middleware('can:CanEditConditions,' . PagePolicy::class)->group(function () {
        Route::get('/conditions/create', [ConditionsController::class, 'create'])->name('conditions.create');
        Route::post('/conditions', [ConditionsController::class, 'store'])->name('conditions.store');
        Route::get('/conditions/{condition}/edit', [ConditionsController::class, 'edit'])->name('conditions.edit');
        Route::put('/conditions/{condition}', [ConditionsController::class, 'update'])->name('conditions.update');
        Route::delete('/conditions/{condition}', [ConditionsController::class, 'destroy'])->name('conditions.destroy');
    });

    // === EVENTS (active events endpoint voor UI) ===
    // kleine toevoeging: endpoint om actieve events op te halen voor de UI
    Route::get('/events/active', [SimulationEventController::class, 'active'])
        ->name('events.active');

});

Route::resource('conditions', ConditionsController::class)->except(['show']);

Route::post('/api/simulation/speed', [SimulationEventController::class, 'changeSpeed']); 

require __DIR__.'/auth.php';
