<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CityFunction;
use App\Models\Effect;

class EffectsController extends Controller
{
    public function index()
    {
        $functions = CityFunction::with('effects')->get();

        $categories = ['veiligheid', 'recreatie', 'milieukwaliteit', 'voorzieningen', 'mobiliteit'];

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

    $currentEffects = Effect::where('city_function_id', $functionId)
        ->pluck('value', 'category')
        ->toArray();

    $changes = [];

    foreach ($newEffects as $category => $newValue) {
        $oldValue = $currentEffects[$category] ?? null;

        if ($oldValue !== $newValue) {
            Effect::where('city_function_id', $functionId)
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
