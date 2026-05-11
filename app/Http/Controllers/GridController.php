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

        $adjacencyResult = $this->checkAdjacency($newRow, $newCol, $function->id);

        if (!$adjacencyResult['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $adjacencyResult['message']
            ], 403);
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

    private function checkAdjacency($row, $col, $newFunctionId)
    {
        $neighbors = [
            ['row' => $row - 1, 'col' => $col],
            ['row' => $row + 1, 'col' => $col],
            ['row' => $row, 'col' => $col - 1],
            ['row' => $row, 'col' => $col + 1],
        ];
    
        foreach ($neighbors as $neighborPos) {
            $neighborCell = GridCell::where('row', $neighborPos['row'])
                                    ->where('col', $neighborPos['col'])
                                    ->first();
    
            if ($neighborCell) {
                $neighborFunctionId = $neighborCell->function_id;
    
                $forbiddenRuleExists = AdjacencyRule::where('type', 'forbidden')
                    ->where(function ($query) use ($newFunctionId, $neighborFunctionId) {
                        $query->where(function ($q) use ($newFunctionId, $neighborFunctionId) {
                            $q->where('function_a', $newFunctionId)->where('function_b', $neighborFunctionId);
                        })->orWhere(function ($q) use ($newFunctionId, $neighborFunctionId) {
                            $q->where('function_a', $neighborFunctionId)->where('function_b', $newFunctionId);
                        });
                    })->exists();
    
                if ($forbiddenRuleExists) {
                    $neighborName = $neighborCell->function->name ?? 'a neighboring building';
                    return [
                        'allowed' => false,
                        'message' => "Placement failed: This building cannot be placed next to a {$neighborName} (Adjacency Rule)."
                    ];
                }
            }
        }
    
        return ['allowed' => true];
    }
}