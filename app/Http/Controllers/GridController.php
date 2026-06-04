<?php

namespace App\Http\Controllers;

use App\Models\CityFunction;
use App\Models\GridCell;
use App\Models\AdjacencyRule;
use App\Models\Condition;
use Illuminate\Http\Request;
use App\Models\UndoAction;
use App\Http\Controllers\QoLController;
use App\Services\EventModifierService;

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
        $functions = CityFunction::orderBy('name')
            ->get()
            ->groupBy('category')
            ->sortKeys();

        $activeEvents = EventModifierService::getActiveEvents();

        return response()
            ->view('gridView', [
                'functions' => $functions,
                'grid' => $grid,
                'activeEvents' => $activeEvents
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

        // Bepaal de logica voor de UndoAction
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

        // Capture previous function id for rollback if needed
        $previousFunctionId = $actionCell ? $actionCell->function_id : null;
        // Sla de UndoAction op, nu inclusief new_row en new_col!
        UndoAction::truncate();
        UndoAction::create([
            'row' => $actionRow,
            'col' => $actionCol,
            'new_row' => $oldRow !== null ? $newRow : null,
            'new_col' => $oldRow !== null ? $newCol : null,
            'previous_function_id' => $actionCell ? $actionCell->function_id : null,
            'action_type' => $oldRow !== null 
                ? 'move' 
                : ($functionId === null 
                    ? 'remove' 
                    : ($actionCell && $actionCell->function_id ? 'replace' : 'insert')),
        ]);

        // 1. Oude cel leegmaken bij een move
        if ($oldRow !== null && $oldCol !== null) {
            GridCell::where('row', $oldRow)
                    ->where('col', $oldCol)
                    ->update(['function_id' => null]);
        }

        // 2. Drag-off (verwijderen van grid)
        if ($functionId === null) {
            GridCell::where('row', $newRow)
                    ->where('col', $newCol)
                    ->update(['function_id' => null]);

            $freshQol = QoLController::recalculateQoL();
            return response()->json([
                'success' => true,
                'qol_data' => $freshQol
            ]);
        }

        $function = CityFunction::find($functionId);
        if (!$function) {
            return response()->json(['error' => 'Function not found'], 404);
        }

        // Adjacency / Naburigheidsregels controleren
        $force = filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN);
        $dirs = [[0, 1], [1, 0], [0, -1], [-1, 0]];

        foreach ($dirs as [$dr, $dc]) {
            $r = $newRow + $dr;
            $c = $newCol + $dc;

            $neighbor = GridCell::where('row', $r)->where('col', $c)->first();

            if ($neighbor && $neighbor->function_id) {
                $neighborFuncId = $neighbor->function_id;
                $fa = min($functionId, $neighborFuncId);
                $fb = max($functionId, $neighborFuncId);

                $rule = AdjacencyRule::where(function($q) use ($fa, $fb) {
                    $q->where('function_a', $fa)->where('function_b', $fb);
                })->orWhere(function($q) use ($fa, $fb) {
                    $q->where('function_a', $fb)->where('function_b', $fa);
                })->first();

                $cond = Condition::where(function($q) use ($fa, $fb) {
                    $q->where(function($q2) use ($fa, $fb) {
                        $q2->where('function_a', $fa)->where('function_b', $fb);
                    })->orWhere(function($q2) use ($fa, $fb) {
                        $q2->where('function_a', $fb)->where('function_b', $fa);
                    });
                })->where('type', 'forbidden')->first();

                if ((($rule && $rule->type === 'forbidden') || $cond) && !$force) {
                    // Rollback: restore previous function id on the action cell (if any)
                    if ($actionCell) {
                        GridCell::where('id', $actionCell->id)
                            ->update(['function_id' => $previousFunctionId]);
                    }

                    return response()->json(['success' => false, 'error' => 'placement_forbidden'], 409);
                }
            }
        }

        // 3. Update of maak de nieuwe cel aan
        GridCell::updateOrCreate(
            ['row' => $newRow, 'col' => $newCol],
            ['function_id' => $function->id]
        );


        $freshQol = QoLController::recalculateQoL();

        return response()->json([
            'success' => true,
            'qol_data' => $freshQol
        ]);
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

        $cell->update(['function_id' => null]);

        $freshQol = QoLController::recalculateQoL();

        return response()->json([
            'success' => true,
            'qol_data' => $freshQol
        ]);
    }
}