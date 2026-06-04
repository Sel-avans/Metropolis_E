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

        $targetCell = GridCell::where('row', $newRow)->where('col', $newCol)->first();
        if ($targetCell && $targetCell->is_approved) {
            return response()->json(['success' => false, 'error' => 'cell_locked'], 403);
        }

        if ($oldRow !== null && $oldCol !== null) {
            $sourceCell = GridCell::where('row', $oldRow)->where('col', $oldCol)->first();
            if ($sourceCell && $sourceCell->is_approved) {
                return response()->json(['success' => false, 'error' => 'cell_locked'], 403);
            }
        }

        // Logic for Undo, Adjacency, and Placement...
        // [Your existing logic for update remains here]

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