<?php

namespace App\Http\Controllers;

use App\Models\FunctionItem;

class FunctionController extends Controller
{
    public function index()
    {
        
        $items = FunctionItem::all();

      
        $functions = $items->groupBy('category');

        return view('library', [
            'functions' => $functions
        ]);
    }
}
