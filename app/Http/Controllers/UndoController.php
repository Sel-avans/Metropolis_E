<?php

namespace App\Http\Controllers;

use App\Models\UndoAction;
use App\Models\GridCell;
use App\Models\CityFunction;

class UndoController extends Controller
{
public function undo()
{
    $undo = UndoAction::first();

    if (!$undo) {
        return response()->json([
            'success' => false,
            'message' => 'nothing_to_undo'
        ]);
    }

    $oldCell = GridCell::where('row', $undo->row)
                       ->where('col', $undo->col)
                       ->first();

    if (!$oldCell) {
        return response()->json([
            'success' => false,
            'message' => 'cell_not_found'
        ], 500);
    }

    $previousFunctionId = $undo->previous_function_id;

    $oldCell->function_id = $previousFunctionId;
    $oldCell->save();

    $cleared = null;
    if ($undo->action_type === 'move' && $undo->new_row !== null && $undo->new_col !== null) {
        GridCell::where('row', $undo->new_row)
                ->where('col', $undo->new_col)
                ->update(['function_id' => null]);

        $cleared = [
            'row' => $undo->new_row,
            'col' => $undo->new_col,
        ];
    }

    $image = null;
    if ($previousFunctionId) {
        $func = CityFunction::find($previousFunctionId);
        if ($func) {
            $image = $func->image;
        }
    }

    UndoAction::truncate();

    $freshQol = \App\Http\Controllers\QoLController::recalculateQoL();

    return response()->json([
        'success' => true,
        'cell' => [
            'row' => $undo->row,
            'col' => $undo->col,
            'function_id' => $previousFunctionId,
            'image' => $image
        ],
        'cleared' => $cleared,
        'qol_data' => $freshQol 
    ]);
}

}
