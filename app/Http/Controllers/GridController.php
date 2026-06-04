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
        // 1. Fetch grid data including functions
        $grid = GridCell::with('function.effects')->get();

        // 2. Filter: If a cell is NOT approved, remove the function data
        // This ensures the view treats these cells as empty upon loading.
        foreach ($grid as $cell) {
            if (!$cell->is_approved) {
                $cell->function_id = null;
                $cell->function = null;
            }
        }

        $items = CityFunction::all();
        $functions = $items->groupBy('category');

        return response()->view('gridView', ['functions' => $functions, 'grid' => $grid]);
    }

    public function update(Request $request)
    {
        $oldRow = $request->input('old_row');
        $oldCol = $request->input('old_col');
        $newRow = intval($request->input('new_row'));
        $newCol = intval($request->input('new_col'));
        $functionId = $request->input('function_id');

        // 1. Fetch the target cell and source cell from the database
        $targetCell = GridCell::where('row', $newRow)->where('col', $newCol)->first();
        $sourceCell = ($oldRow !== null && $oldCol !== null) 
                      ? GridCell::where('row', $oldRow)->where('col', $oldCol)->first() 
                      : null;

        // 2. Security check: prevent modification if either cell is approved (locked)
        if ($targetCell && $targetCell->is_approved) {
            $message = $targetCell->function_id
                ? "You can't replace the function in this area"
                : "You can't add a function in this area";

            return response()->json([
                'success' => false,
                'error' => 'cell_locked',
                'message' => $message,
            ], 403);
        }

        if ($sourceCell && $sourceCell->is_approved) {
            return response()->json([
                'success' => false,
                'error' => 'cell_locked',
                'message' => "You can't replace the function in this area",
            ], 403);
        }

        // 3. Remove the function from the source cell if moving from an existing position
        if ($sourceCell) {
            $sourceCell->function_id = null;
            $sourceCell->save();
        }

        // 4. Assign the function to the target cell
        if ($targetCell) {
            $targetCell->function_id = $functionId;
            $targetCell->save();
        }

        // 5. Recalculate Quality of Life metrics and return response
        $freshQol = QoLController::recalculateQoL();
        return response()->json(['success' => true, 'qol_data' => $freshQol]);
    }

    public function approveCell(Request $request)
    {
        if (!auth()->user() || auth()->user()->role->name !== 'City_planner') {
            return response()->json(['success' => false, 'error' => 'unauthorized'], 403);
        }

        $cell = GridCell::where('row', $request->input('row'))
                        ->where('col', $request->input('col'))
                        ->first();

        if (!$cell) return response()->json(['success' => false, 'error' => 'cell_not_found'], 404);

        $cell->is_approved = !$cell->is_approved;
        $cell->save();

        return response()->json(['success' => true, 'is_approved' => $cell->is_approved]);
    }
}