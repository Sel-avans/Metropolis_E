<?php

namespace App\Http\Controllers;

use App\Models\CityFunction;
use App\Models\GridCell;
use App\Models\AdjacencyRule;
use Illuminate\Http\Request;
use App\Models\UndoAction;

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
        $oldRow = $request->input('old_row');
        $oldCol = $request->input('old_col');
        $newRow = intval($request->input('new_row'));
        $newCol = intval($request->input('new_col'));
        $functionId = $request->input('function_id');

        if ($oldRow !== null && $oldCol !== null) {
            $actionRow = $oldRow;
            $actionCol = $oldCol;
        } else {
            $actionRow = $newRow;
            $actionCol = $newCol;
        }

        $actionCell = GridCell::where('row', $actionRow)
                            ->where('col', $actionCol)
                            ->first();


        UndoAction::truncate();
        UndoAction::create([
            'row' => $actionRow,
            'col' => $actionCol,
            'new_row' => $newRow,
            'new_col' => $newCol,
            'previous_function_id' => $actionCell ? $actionCell->function_id : null,
            'action_type' => $oldRow !== null 
                ? 'move' 
                : ($functionId === null 
                    ? 'remove' 
                    : ($actionCell && $actionCell->function_id ? 'replace' : 'insert')),
        ]);



        if ($oldRow !== null && $oldCol !== null) {
            GridCell::where('row', $oldRow)
                    ->where('col', $oldCol)
                    ->update(['function_id' => null]);
        }

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

        $force = filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN);

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

                if ($rule && $rule->type === 'forbidden' && !$force) {
                    return response()->json([
                        'success' => false,
                        'error' => 'placement_forbidden'
                    ], 409);
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
    UndoAction::truncate();

    UndoAction::create([
        'row' => $cell->row,
        'col' => $cell->col,
        'new_row' => null,
        'new_col' => null,
        'previous_function_id' => $cell->function_id,
        'action_type' => 'remove',
    ]);

    $cell->update([
        'function_id' => null
    ]);

    \App\Http\Controllers\QoLController::recalculateQoL();

    return response()->json([
        'success' => true
    ]);
}


}
