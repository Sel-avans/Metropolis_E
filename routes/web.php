<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GridController;
use App\Http\Controllers\QoLController;
use App\Http\Controllers\FunctionController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/grid', [GridController::class, 'index']);

Route::get('/library', [FunctionController::class, 'index'])->name('library');

Route::get('/effects', function () {
    return view('effects.index');
})->name('effects.index');

Route::post('/grid/update', [GridController::class, 'update']);

Route::get('/qol/details', [QoLController::class, 'details'])->name('qol.details');
