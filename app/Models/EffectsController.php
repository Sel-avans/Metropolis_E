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
            'category' => 'required|string',
            'value' => 'required|integer|min:-5|max:5',
        ]);

        Effect::where('city_function_id', $request->function_id)
            ->where('category', $request->category)
            ->update(['value' => $request->value]);

        return response()->json(['success' => true]);
    }
}
