<?php

namespace App\Http\Controllers;

use App\Models\UndoAction;
use App\Models\GridCell;
use App\Models\CityFunction;
<<<<<<< Updated upstream
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\QoLController;
=======
use App\Http\Controllers\QoLController; // Zorg dat deze correct is geïmporteerd
>>>>>>> Stashed changes

class UndoController extends Controller
{
    public function undo()
    {
<<<<<<< Updated upstream
        // Pak altijd de LAATSTE actie die is uitgevoerd (meest logisch voor undo)
        $undo = UndoAction::latest()->first();

        if (!$undo) {
            return response()->json([
                'success' => false,
                'message' => 'nothing_to_undo'
            ]);
        }

        // We voeren dit uit in een database transactie voor de veiligheid
        return DB::transaction(function () use ($undo) {
            
            // Zoek de specifieke cel op die hersteld moet worden
            $oldCell = GridCell::where('row', $undo->row)
                               ->where('col', $undo->col)
                               ->first();

            if (!$oldCell) {
                return response()->json([
                    'success' => false,
                    'message' => 'cell_not_found'
                ], 500);
            }

            // Herstel de oude functie op de originele locatie
            $previousFunctionId = $undo->previous_function_id;
            $oldCell->function_id = $previousFunctionId;
            $oldCell->save();

            $cleared = null;

            // Als het een 'move' was, moeten we de nieuwe locatie (waar hij naartoe was gesleept) weer leegmaken
            if ($undo->action_type === 'move' && $undo->new_row !== null && $undo->new_col !== null) {
                GridCell::where('row', $undo->new_row)
                        ->where('col', $undo->new_col)
                        ->update(['function_id' => null]);

                $cleared = [
                    'row' => $undo->new_row,
                    'col' => $undo->new_col,
                ];
            }

            // Afbeelding ophalen voor de frontend update
            $image = null;
            if ($previousFunctionId) {
                $func = CityFunction::find($previousFunctionId);
                if ($func) {
                    $image = $func->image;
                }
            }

            // Verwijder ALLEEN deze specifieke undo-actie, niet de hele tabel!
            $undo->delete();

            // Bereken de QoL direct volledig opnieuw op basis van de herstelde situatie
            $freshQol = QoLController::recalculateQoL();

            return response()->json([
                'success' => true,
                'cell' => [
                    'row' => $undo->row,
                    'col' => $undo->col,
                    'function_id' => $previousFunctionId,
                    'image' => $image
                ],
                'cleared' => $cleared,
                'qol_data' => $freshQol // De frontend krijgt nu ook hier de exacte, schone QoL score
            ]);
        });
=======
        // Pak de actie die in de tabel staat
        $undo = UndoAction::first();

        if (!$undo) {
            return response()->json([
                'success' => false,
                'message' => 'nothing_to_undo'
            ]);
        }

        // Zoek de specifieke cel op basis van de opgeslagen rij en kolom
        $oldCell = GridCell::where('row', $undo->row)
                           ->where('col', $undo->col)
                           ->first();

        if (!$oldCell) {
            return response()->json([
                'success' => false,
                'message' => 'cell_not_found'
            ], 500);
        }

        // Herstel de oude functie op de originele plek
        $previousFunctionId = $undo->previous_function_id;
        $oldCell->function_id = $previousFunctionId;
        $oldCell->save();

        // Omdat we geen 'new_row' of 'new_col' in de database hebben,
        // bepalen we de 'cleared' cellen en eventuele verplaatsingen in de frontend,
        // of we herstellen puur de basiscel.
        $cleared = null;

        // Afbeelding ophalen voor de frontend zodat het icoontje terugkeert
        $image = null;
        if ($previousFunctionId) {
            $func = CityFunction::find($previousFunctionId);
            if ($func) {
                $image = $func->image;
            }
        }

        // Maak de undo-tabel leeg (truncate) zodat de actie is verbruikt
        UndoAction::truncate();

        // BEREKEN DE QOL OPNIEUW NA DE UNDO
        $freshQol = QoLController::recalculateQoL();

        return response()->json([
            'success' => true,
            'cell' => [
                'row' => $undo->row,
                'col' => $undo->col,
                'function_id' => $previousFunctionId,
                'image' => $image
            ],
            'cleared' => $cleared,
            'qol_data' => $freshQol // Dit lost Bug 2 op na een undo!
        ]);
>>>>>>> Stashed changes
    }
}