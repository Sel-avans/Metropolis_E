<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
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

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});




require __DIR__.'/auth.php';


Route::post('/save-cell', function (Request $request) {
    // Valideer of we alle data netjes binnenkrijgen
    $validated = $request->validate([
        'x' => 'required|integer',
        'y' => 'required|integer',
        'city_function_id' => 'required|exists:city_functions,id'
    ]);

    
    GridState::updateOrCreate(
        ['x' => $validated['x'], 'y' => $validated['y']],
        
        ['city_function_id' => $validated['city_function_id']] 
    );

    return response()->json(['status' => 'success']);
});