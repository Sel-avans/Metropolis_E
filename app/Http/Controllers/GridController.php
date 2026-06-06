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
        $grid = GridCell::with('function.effects')->get();
        $functions = CityFunction::orderBy('name')->get()->groupBy('category')->sortKeys();

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
        // Security check
        if (!auth()->user() || auth()->user()->role->name !== 'City_planner') {
            return response()->json(['success' => false, 'error' => 'unauthorized'], 403);
        }

        // Retrieve the array of cells from the request payload
        $cellsData = $request->input('cells', []);

        // Fallback to support the old single-cell format (if needed)
        if ($request->has('row') && $request->has('col')) {
            $cellsData[] = ['row' => $request->input('row'), 'col' => $request->input('col')];
        }

        if (empty($cellsData)) {
            return response()->json(['success' => false, 'error' => 'no_cells_provided'], 400);
        }

        $updatedCells = [];

        // Process each cell in the array
        foreach ($cellsData as $cellData) {
            $cell = GridCell::where('row', $cellData['row'])
                            ->where('col', $cellData['col'])
                            ->first();

            if ($cell) {
                // Toggle the approval state
                $cell->is_approved = !$cell->is_approved;
                $cell->save();
                
                // Track the updated state to return to the frontend
                $updatedCells[] = [
                    'row' => $cell->row,
                    'col' => $cell->col,
                    'is_approved' => $cell->is_approved
                ];
            }
        }

        return response()->json(['success' => true, 'updated_cells' => $updatedCells]);
    }
}