<?php

namespace App\Http\Controllers;

use App\Models\UndoAction;
use App\Models\GridCell;
use App\Models\CityFunction;
use App\Http\Controllers\QoLController;

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

        // 1. Herstel de oude cel (Zet het gebouw terug waar het vandaan kwam)
        $oldCell = GridCell::where('row', $undo->row)
                           ->where('col', $undo->col)
                           ->first();

        if ($oldCell) {
            $oldCell->function_id = $undo->previous_function_id;
            $oldCell->save();
        }

        // 2. Als het een verplaatsing (move) was, maak de NIEUWE cel dan weer leeg
        $cleared = null;
        if ($undo->action_type === 'move' && $undo->new_row !== null && $undo->new_col !== null) {
            GridCell::where('row', $undo->new_row)
                    ->where('col', $undo->new_col)
                    ->update(['function_id' => null]);
            
            $cleared = [
                'row' => $undo->new_row,
                'col' => $undo->new_col
            ];
        }

        // Afbeelding ophalen voor de frontend UI herstel
        $image = null;
        if ($undo->previous_function_id) {
            $func = CityFunction::find($undo->previous_function_id);
            if ($func) {
                $image = $func->image;
            }
        }

        UndoAction::truncate();

        // Herbereken direct de QoL score na de undo herstelactie
        $freshQol = QoLController::recalculateQoL();

        return response()->json([
            'success' => true,
            'cell' => [
                'row' => $undo->row,
                'col' => $undo->col,
                'function_id' => $undo->previous_function_id,
                'image' => $image
            ],
            'cleared' => $cleared,
            'qol_data' => $freshQol
        ]);
    }
}