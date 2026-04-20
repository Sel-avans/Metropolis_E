<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GridController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/grid', [GridController::class, 'index']);
