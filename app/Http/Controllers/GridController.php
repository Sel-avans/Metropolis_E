<?php

namespace App\Http\Controllers;

use App\Models\CityFunction;
use App\Models\GridCell;
use App\Models\AdjacencyRule;
use App\Models\Condition;
use Illuminate\Http\Request;
use App\Models\UndoAction;
use App\Http\Controllers\QoLController;

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

        // SECURITY: Check if the target cell is already approved/locked
        $targetCell = GridCell::where('row', $newRow)->where('col', $newCol)->first();
        if ($targetCell && $targetCell->is_approved) {
            return response()->json(['success' => false, 'error' => 'cell_locked'], 403);
        }

        // SECURITY: If moving a function, the source cell must not be locked either
        if ($oldRow !== null && $oldCol !== null) {
            $sourceCell = GridCell::where('row', $oldRow)->where('col', $oldCol)->first();
            if ($sourceCell && $sourceCell->is_approved) {
                return response()->json(['success' => false, 'error' => 'cell_locked'], 403);
            }
        }

        // Determine the logic for the UndoAction
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
        
        // Save the UndoAction, now including new_row and new_col
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

        // 1. Clear the old cell during a move action
        if ($oldRow !== null && $oldCol !== null) {
            GridCell::where('row', $oldRow)
                    ->where('col', $oldCol)
                    ->update(['function_id' => null]);
        }

        // 2. Drag-off (removing from grid)
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

        // Check adjacency rules
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
                    $q->where('function_a', $fa)->where('function_b', $fb);
                })->orWhere(function($q) use ($fa, $fb) {
                    $q->where('function_a', $fb)->where('function_b', $fa);
                })->where('type', 'forbidden')->first();

                if ((($rule && $rule->type === 'forbidden') || $cond) && !$force) {
                    // Rollback: restore previous function id on the action cell (if any)
                    if ($actionCell) {
                        $actionCell->update(['function_id' => $previousFunctionId]);
                    }

                    return response()->json(['success' => false, 'error' => 'placement_forbidden'], 409);
                }
            }
        }

        // 3. Update or create the new grid cell
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
        // SECURITY: If a cell is approved/locked, it cannot be removed
        if ($cell->is_approved) {
            return response()->json(['success' => false, 'error' => 'cell_locked'], 403);
        }

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

    // NEW METHOD: Approve and lock/unlock a specific grid cell
    public function approveCell(Request $request)
    {
        // Only the City Planner is authorized via the backend
        if (!auth()->user() || auth()->user()->role->name !== 'City_planner') {
            return response()->json(['success' => false, 'error' => 'unauthorized'], 403);
        }

        $row = $request->input('row');
        $col = $request->input('col');

        $cell = GridCell::where('row', $row)->where('col', $col)->first();

        if (!$cell) {
            return response()->json(['success' => false, 'error' => 'cell_not_found'], 444);
        }

        // Toggle the approval status
        $cell->is_approved = !$cell->is_approved;
        $cell->save();

        return response()->json([
            'success' => true,
            'is_approved' => $cell->is_approved
        ]);
    }
}