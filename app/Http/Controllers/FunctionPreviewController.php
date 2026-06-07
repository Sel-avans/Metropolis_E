<?php

namespace App\Http\Controllers;

use App\Models\CityFunction;
use App\Models\Condition;

class FunctionPreviewController extends Controller
{
    // Geeft effects en conditions terug van een destination als JSON
    public function show($id)
    {
        // 'effects' direct laden voorkomt een extra database query.
        $function = CityFunction::with('effects')->findOrFail($id);

        // De where en orWhere zijn gegroepeerd om database-indexen optimaal te gebruiken.
        $conditions = Condition::with(['functionA', 'functionB'])
            ->where(function ($query) use ($id) {
                $query->where('function_a', $id)
                      ->orWhere('function_b', $id);
            })
            ->get();

        // Groepeer effects per categorie
        $effects = $function->effects->map(function ($effect) {
            return [
                'category' => $effect->category,
                'value'    => $effect->value,
            ];
        });

        // Maak conditions leesbaar
        $conditionList = $conditions->map(function ($condition) use ($id) {
            //  ?-> gebruikt voor extra veiligheid, mocht een gerelateerd model null zijn
            $other = ($condition->function_a == $id)
                ? ($condition->functionB?->name ?? 'Onbekende functie')
                : ($condition->functionA?->name ?? 'Onbekende functie');

            return [
                'type'  => $condition->type,
                'value' => $condition->value,
                'with'  => $other,
            ];
        });

        return response()->json([
            'name'       => $function->name,
            'effects'    => $effects,
            'conditions' => $conditionList,
        ]);
    }
}