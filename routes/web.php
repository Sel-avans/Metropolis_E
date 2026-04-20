<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FunctionController;
use App\Models\CityFunction;
use App\Models\GridState;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/grid', function () {
    $functions = \App\Models\CityFunction::all();
    $savedCells = \App\Models\GridState::with('cityFunction')->get();
    
    return view('gridView', compact('functions', 'savedCells'));
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/library', [FunctionController::class, 'index']);

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::post('/save-cell', function (Request $request) {
    // Valideer de binnenkomende gegevens, inclusief optionele oude coördinaten
    $validated = $request->validate([
        'x' => 'required|integer',
        'y' => 'required|integer',
        'city_function_id' => 'required|exists:city_functions,id',
        'oldX' => 'nullable|integer',
        'oldY' => 'nullable|integer',
    ]);

// Als er oude coördinaten worden opgegeven, betekent dit dat we een item verplaatsen.

// Verwijder eerst het record op de vorige locatie.
    if ($request->filled('oldX') && $request->filled('oldY')) {
        GridState::where('x', $validated['oldX'])
                 ->where('y', $validated['oldY'])
                 ->delete();
    }

    // Save the new position or update it if it already exists
    GridState::updateOrCreate(
        ['x' => $validated['x'], 'y' => $validated['y']],
        ['city_function_id' => $validated['city_function_id']] 
    );

    return response()->json(['status' => 'success']);
});