<?php

namespace App\Http\Controllers;

use App\Models\CityFunction;
use App\Models\GridCell;
use App\Models\AdjacencyRule;
use Illuminate\Http\Request;

class GridController extends Controller
{
    public function index()
{
    for ($row = 1; $row <= 4; $row++) {
        for ($col = 1; $col <= 3; $col++) {
            GridCell::firstOrCreate([
                'row' => $row,
                'col' => $col
            ]);
        }
    }

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

    // Clear the old cell if moving
    if ($oldRow !== null && $oldCol !== null) {
        GridCell::where('row', $oldRow)
                ->where('col', $oldCol)
                ->update(['function_id' => null]);
    }

    // If no function provided, clear the target cell
    if ($functionId === null) {
        GridCell::where('row', $newRow)
                ->where('col', $newCol)
                ->update(['function_id' => null]);

        return response()->json(['success' => true]);
    }

    $function = CityFunction::find($functionId);

    if (!$function) {
        return response()->json(['error' => 'Function not found'], 404);
    }

    // Check adjacency rules with immediate neighbors
    $force = $request->has('force') ? filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN) : false;

    $dirs = [
        [0, 1],
        [1, 0],
        [0, -1],
        [-1, 0],
    ];

    foreach ($dirs as [$dr, $dc]) {
        $r = $newRow + $dr;
        $c = $newCol + $dc;

        $neighbor = GridCell::where('row', $r)->where('col', $c)->first();

        if ($neighbor && $neighbor->function_id) {
            $neighborFuncId = $neighbor->function_id;

            $fa = min($functionId, $neighborFuncId);
            $fb = max($functionId, $neighborFuncId);

            $rule = AdjacencyRule::where('function_a', $fa)
                ->where('function_b', $fb)
                ->first();

            if ($rule && $rule->type === 'forbidden') {
                if (!$force) {
                    return response()->json([
                        'success' => false,
                        'error' => 'placement_forbidden'
                    ], 409);
                }
                // if forced, allow placement despite the rule
            }
        }
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

public function removeFunction(GridCell $cell)
{
    $cell->update([
        'function_id' => null
    ]);

    return response()->json([
        'success' => true
    ]);
}


}
