<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Models\Destination;
use App\Models\GridState;
use Illuminate\Http\Request;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/grid', function () {
    $destinations = Destination::all();
    $savedCells = \App\Models\GridState::with('destination')->get();
    return view('gridView', compact('destinations', 'savedCells'));
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
        'destination_id' => 'required|integer|exists:destinations,id'
    ]);

    
    GridState::updateOrCreate(
        ['x' => $validated['x'], 'y' => $validated['y']],
        ['destination_id' => $validated['destination_id']]
    );

    return response()->json(['status' => 'success']);
});
