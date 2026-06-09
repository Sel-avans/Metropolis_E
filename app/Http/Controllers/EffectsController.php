<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CityFunction;
use App\Models\Effect;

class EffectsController extends Controller
{
    public function index()
    {
        $categories = [
            'safety',
            'recreation',
            'environment',
            'amenities',
            'mobility'
        ];

        $functions = CityFunction::with('effects')->get();

        return view('effects.index', compact('functions', 'categories'));
    }

    public function update(Request $request)
{
    $request->validate([
        'function_id' => 'required|integer',
        'effects'     => 'required|array'
    ]);

    $functionId = $request->function_id;
    $effects = $request->effects;

    foreach ($effects as $category => $value) {
        Effect::where('function_id', $functionId)
            ->whereNull('simulation_event_id')
            ->where('category', $category)
            ->update(['value' => $value]);
    }

    return response()->json(['success' => true]);
}
}
