<?php

namespace App\Http\Controllers;

use App\Models\CityFunction;
use Illuminate\Http\Request;
use App\Models\GridCell;

class GridController extends Controller
{
    public function index()
    {
        $grid = GridCell::with('function.effects')->get();
        $items = CityFunction::all();
        $functions = $items->groupBy('category');

        return response()
            ->view('gridView', [
                'functions' => $functions,
                'grid' => $grid
            ])
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function update(Request $request)
{
    $oldRow = $request->has('old_row') ? intval($request->input('old_row')) : null;
    $oldCol = $request->has('old_col') ? intval($request->input('old_col')) : null;
    $newRow = intval($request->input('new_row'));
    $newCol = intval($request->input('new_col'));
    $functionId = $request->input('function_id');
    
    if ($oldRow !== null && $oldCol !== null) {
        GridCell::where('row', $oldRow)
                ->where('col', $oldCol)
                ->delete();
    }

    if ($functionId === null) {
    GridCell::where('row', $newRow)
            ->where('col', $newCol)
            ->delete();

        return response()->json(['success' => true]);
    }

    $function = CityFunction::find($functionId);

    if (!$function) {
        return response()->json(['error' => 'Function not found'], 404);
    }

    GridCell::updateOrCreate(
        [
            'row' => $newRow,
            'col' => $newCol
        ],
        [
            'function_id' => $function->id
        ]
    );

    return response()->json(['success' => true]);
}

}
