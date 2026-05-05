<?php

namespace App\Http\Controllers;

use App\Models\CityFunction;

class FunctionController extends Controller
{
    public function index()
    {
        $items = CityFunction::all();
        $functions = $items->groupBy('category');

        return view('library', [
            'functions' => $functions
        ]);
    }
}
