<?php

namespace App\Http\Controllers;

use App\Models\CityFunction;
use App\Models\GridCell;
use App\Models\AdjacencyRule;
use App\Models\UndoAction;
use Illuminate\Http\Request;
use App\Http\Controllers\QoLController;

class GridController extends Controller
{
    /**
     * Toont de grid en de library met functies.
     */
    public function index()
    {
        // Zorg ervoor dat de grid (4x3) altijd bestaat in de database
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

    /**
     * Behandelt zowel verplaatsingen (move) als nieuwe plaatsingen (insert/replace)
     * en verwijderingen via drag-off.
     */
    public function update(Request $request)
    {
        $oldRow = $request->input('old_row');
        $oldCol = $request->input('old_col');
        $newRow = $request->input('new_row') !== null ? intval($request->input('new_row')) : null;
        $newCol = $request->input('new_col') !== null ? intval($request->input('new_col')) : null;
        $functionId = $request->input('function_id');

        // Bepaal het type actie vooraf om fouten te voorkomen
        $isMove = ($oldRow !== null && $oldCol !== null && $newRow !== null && $newCol !== null);
        $isRemoveDragOff = ($functionId === null && $newRow !== null && $newCol !== null);

        // --- 1. AFHANDELING VAN REMOVE (via drag-off van grid naar library) ---
        if ($isRemoveDragOff) {
            $targetCell = GridCell::where('row', $newRow)->where('col', $newCol)->first();
            
            UndoAction::truncate();
            UndoAction::create([
                'row' => $newRow,
                'col' => $newCol,
                'new_row' => null,
                'new_col' => null,
                'previous_function_id' => $targetCell ? $targetCell->function_id : null,
                'action_type' => 'remove',
            ]);

            if ($targetCell) {
                $targetCell->update(['function_id' => null]);
            }

            // Bereken QoL direct opnieuw na verwijderen via drag-off
            $freshQol = QoLController::recalculateQoL();
            return response()->json(['success' => true, 'qol_data' => $freshQol]);
        }

        // --- 2. AFHANDELING VAN MOVE OF INSERT/REPLACE ---
        $newCell = GridCell::where('row', $newRow)->where('col', $newCol)->first();

        // Log de undo-actie op basis van de exacte situatie
        UndoAction::truncate();
        
        if ($isMove) {
            // Bij een verplaatsing (move) loggen we waar hij vandaan kwam ($oldRow/$oldCol)
            // zodat de undo-knop hem exact daar weer terug kan zetten.
            $sourceCell = GridCell::where('row', $oldRow)->where('col', $oldCol)->first();
            
            UndoAction::create([
                'row' => $oldRow,
                'col' => $oldCol,
                'new_row' => $newRow,
                'new_col' => $newCol,
                'previous_function_id' => $sourceCell ? $sourceCell->function_id : $functionId,
                'action_type' => 'move',
            ]);
        } else {
            // Slepen vanuit de library naar de grid (Insert of Replace op een bezette cel)
            UndoAction::create([
                'row' => $newRow,
                'col' => $newCol,
                'new_row' => null,
                'new_col' => null,
                'previous_function_id' => $newCell ? $newCell->function_id : null,
                'action_type' => $newCell && $newCell->function_id ? 'replace' : 'insert',
            ]);
        }

        // Maak de oude cel leeg als het een verplaatsing betreft
        if ($oldRow !== null && $oldCol !== null) {
            GridCell::where('row', $oldRow)
                    ->where('col', $oldCol)
                    ->update(['function_id' => null]);
        }

        // Valideer of de te plaatsen functie bestaat
        $function = CityFunction::find($functionId);
        if (!$function) {
            return response()->json(['error' => 'Function not found'], 404);
        }

        // Naburigheidsregels (AdjacencyRules) controleren
        $force = filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN);
        $dirs = [[0, 1], [1, 0], [0, -1], [-1, 0]]; // Boven, rechts, onder, links

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

        // Plaats of update de functie op de nieuwe cel
        GridCell::updateOrCreate(
            ['row' => $newRow, 'col' => $newCol],
            ['function_id' => $function->id]
        );

        // Bereken de QoL geforceerd opnieuw op basis van de actuele database status
        $freshQol = QoLController::recalculateQoL();

        return response()->json([
            'success' => true,
            'qol_data' => $freshQol // Stuur de schone QoL data terug naar de frontend
        ]);
    }

    /**
     * Verwijdert een functie via het kruisje (specifiek op cel-niveau).
     */
    public function removeFunction(GridCell $cell)
    {
        UndoAction::truncate();

        // Sla de undo-actie op voor exact deze specifieke celcoördinaten
        UndoAction::create([
            'row' => $cell->row,
            'col' => $cell->col,
            'new_row' => null,
            'new_col' => null,
            'previous_function_id' => $cell->function_id,
            'action_type' => 'remove',
        ]);

        // Alleen deze specifieke cel leegmaken (voorkomt duplicaten-bug)
        $cell->update([
            'function_id' => null
        ]);

        // Bereken de QoL opnieuw
        $freshQol = QoLController::recalculateQoL();

        return response()->json([
            'success' => true,
            'qol_data' => $freshQol
        ]);
    }
}