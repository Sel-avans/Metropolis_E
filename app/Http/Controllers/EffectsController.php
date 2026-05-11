<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CityFunction;
use App\Models\Effect;

class EffectsController extends Controller
{

    private $allowedCategories = [
        'veiligheid',
        'recreatie',
        'milieukwaliteit',
        'voorzieningen',
        'mobiliteit'
    ];

    public function index()
{
    $functions = CityFunction::with('effects')
        ->orderBy('category')
        ->orderBy('name')
        ->get();

    $categories = $this->allowedCategories;

    return view('effects.index', compact('functions', 'categories'));
}

public function update(Request $request)
{
    $request->validate([
        'function_id' => 'required|integer',
        'effects' => 'required|array'
    ]);

    $functionId = $request->function_id;
    $newEffects = $request->effects;

        $normalizedEffects = [];

        foreach ($newEffects as $category => $value) {
            $normalized = strtolower(trim($category));

            if (!in_array($normalized, $this->allowedCategories)) {
                return response()->json([
                    'success' => false,
                    'message' => "Ongeldige categorie: $category"
                ]);
            }

            $normalizedEffects[$normalized] = $value;
        }

    $currentEffects = Effect::where('function_id', $functionId)
        ->pluck('value', 'category')
        ->toArray();

    $changes = [];

    foreach ($newEffects as $category => $newValue) {
        $oldValue = $currentEffects[$category] ?? null;

        if ($oldValue !== $newValue) {
            Effect::where('function_id', $functionId)
                ->where('category', $category)
                ->update(['value' => $newValue]);

            $changes[$category] = [
                'old' => $oldValue,
                'new' => $newValue
            ];
        }
    }

    if (empty($changes)) {
        return response()->json([
            'success' => false,
            'message' => 'Geen wijzigingen'
        ]);
    }

    return response()->json([
        'success' => true,
        'changed' => $changes
    ]);
}
}
