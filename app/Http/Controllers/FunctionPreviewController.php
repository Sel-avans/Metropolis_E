<?php

namespace App\Http\Controllers;

use App\Models\CityFunction;
use App\Models\Condition;

class FunctionPreviewController extends Controller
{
    // Geeft effects en conditions terug van een destination als JSON
    public function show($id)
    {
        $function = CityFunction::with('effects')->findOrFail($id);

        // Haal alle conditions op waar deze destination in voorkomt
        $conditions = Condition::with(['functionA', 'functionB'])
            ->where('function_a', $id)
            ->orWhere('function_b', $id)
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
            $other = ($condition->function_a == $id)
                ? $condition->functionB->name
                : $condition->functionA->name;

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