<?php

namespace App\Http\Controllers;

use App\Models\CityFunction;
use Illuminate\Http\Request;
use App\Models\GridCell;

class GridController extends Controller
{
public function index()
{
    $items = CityFunction::all();
    $functions = $items->groupBy('category');

    $grid = GridCell::with('function')->get();

    return view('gridView', [
        'functions' => $functions,
        'grid' => $grid
    ]);
}


public function update(Request $request)
{
    $row = $request->input('row');
    $col = $request->input('col');
    $name = $request->input('function');

    if (!$row || !$col) {
        return response()->json(['error' => 'Missing row/col'], 400);
    }

    if (!$name) {
        GridCell::where('row', $row)->where('col', $col)->delete();
        return response()->json(['success' => true]);
    }

    $function = CityFunction::where('name', $name)->first();

    if (!$function) {
        return response()->json(['error' => 'Function not found'], 404);
    }

    GridCell::where('row', $row)->where('col', $col)->delete();

    GridCell::create([
        'row' => $row,
        'col' => $col,
        'city_function_id' => $function->id
    ]);

    return response()->json(['success' => true]);
}
}
