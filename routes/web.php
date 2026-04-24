<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\GridController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QoLController;
use App\Http\Controllers\FunctionController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/grid', [GridController::class, 'index'])->name('grid');
Route::get('/library', [FunctionController::class, 'index'])->name('library');

Route::get('/effects', function () {
    return view('effects.index');
})->name('effects.index');

Route::post('/grid/update', [GridController::class, 'update']);
Route::get('/qol/details', [QoLController::class, 'details'])->name('qol.details');


Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
