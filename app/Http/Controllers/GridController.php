<?php

namespace App\Http\Controllers;

use App\Models\CityFunction;
use App\Models\GridCell;
use App\Models\AdjacencyRule;
use App\Models\Condition;
use Barryvdh\DomPDF\Facade\Pdf;
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
        $items = CityFunction::all();
        $functions = $items->groupBy('category');

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

        $targetCell = GridCell::where('row', $newRow)
            ->where('col', $newCol)
            ->first();

        // Prevent changes to approved (locked) cells
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

        $displacedFunctionId = $targetCell?->function_id;
        $isSwap = $oldRow !== null
            && $oldCol !== null
            && $displacedFunctionId !== null
            && $functionId !== null
            && (int) $displacedFunctionId !== (int) $functionId;

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
                ? ($isSwap ? 'swap' : 'move')
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

        // 3. Update of maak de nieuwe cel aan (of swap twee gridcellen)
        if ($isSwap) {
            GridCell::where('row', $oldRow)
                ->where('col', $oldCol)
                ->update(['function_id' => $displacedFunctionId]);

            GridCell::where('row', $newRow)
                ->where('col', $newCol)
                ->update(['function_id' => $function->id]);
        } else {
            GridCell::updateOrCreate(
                ['row' => $newRow, 'col' => $newCol],
                ['function_id' => $function->id]
            );
        }


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

    public function exportPdf()
    {
        try {
            \Log::info('PDF export started');
            
            $grid = GridCell::with('function.effects')->get();
            \Log::info('Grid loaded with ' . $grid->count() . ' cells');
            
            // Get QoL data for PDF report
            $qolData = QoLController::computeQoLData();
            \Log::info('QoL data computed');

            foreach ($grid as $cell) {
                if ($cell->function && !empty($cell->function->image)) {
                    $imageUri = $this->convertImageToDataUri($cell->function->image);
                    if ($imageUri) {
                        $cell->function->image_base64 = $imageUri;
                    }
                }
            }
            \Log::info('Images converted to base64');

            $pdf = Pdf::loadView('pdf.simulation-report', [
                'grid' => $grid,
                'qolData' => $qolData,
                'exportedAt' => now()->toDateTimeString(),
            ])->setPaper('a4', 'portrait');

            $filename = 'simulation-report-' . now()->format('Y-m-d_H-i-s') . '.pdf';
            \Log::info('PDF generated with filename: ' . $filename);

            return $pdf->download($filename);
        } catch (\Exception $e) {
            \Log::error('PDF export error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'PDF generation failed: ' . $e->getMessage()], 500);
        }
    }

    private function convertImageToDataUri(string $relativePath): ?string
    {
        $path = public_path($relativePath);

        if (!file_exists($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        $mimeType = mime_content_type($path) ?: 'image/png';

        return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
    }

    public function approveCell(Request $request)
    {
        // Security check: ensure the user has an authorized role
        // (UI shows this button to Municipal_Policy_Maker and Administrator too)
        $allowedRoles = ['City_planner', 'Municipal_Policy_Maker', 'Administrator'];
        if (!auth()->user() || !in_array(auth()->user()->role->name, $allowedRoles, true)) {
            return response()->json(['success' => false, 'error' => 'unauthorized'], 403);
        }

        $cellsData = $request->input('cells', []);

        // Fallback to support the old single-cell format
        if (empty($cellsData) && $request->has('row') && $request->has('col')) {
            $cellsData[] = ['row' => $request->input('row'), 'col' => $request->input('col')];
        }

        if (empty($cellsData)) {
            return response()->json(['success' => false, 'error' => 'no_cells_provided'], 400);
        }

        $updatedCells = [];

        foreach ($cellsData as $cellData) {
            // Force javascript strings to integers to ensure strict database matching
            $row = intval($cellData['row']);
            $col = intval($cellData['col']);

            // Find the cell in the database
            $cell = GridCell::where('row', $row)->where('col', $col)->first();

            // If the cell does not exist in the database yet, create it on the fly
            if (!$cell) {
                $cell = new GridCell();
                $cell->row = $row;
                $cell->col = $col;
                $cell->is_approved = false; // Default state, will be toggled below
            }

            // Toggle the approval state
            $cell->is_approved = !$cell->is_approved;
            $cell->save(); // Save to the database
            
            // Track the updated state to return to the frontend
            $updatedCells[] = [
                'row' => $cell->row,
                'col' => $cell->col,
                'is_approved' => $cell->is_approved
            ];
        }

        return response()->json(['success' => true, 'updated_cells' => $updatedCells]);
    }
}