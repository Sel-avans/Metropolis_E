<?php

namespace App\Http\Controllers;

use App\Models\Condition;
use App\Models\CityFunction;
use Illuminate\Http\Request;

class ConditionController extends Controller
{
    public function index()
    {
        return Condition::with(['functionA', 'functionB'])->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'function_a_id' => 'required|exists:city_functions,id',
            'function_b_id' => 'required|exists:city_functions,id',
            'type' => 'required|in:forbidden,allowed,bonus,penalty',
            'value' => 'nullable|integer|min:-5|max:5'
        ]);

        Condition::validateRule($data);

        $rule = Condition::create($data);

        return response()->json($rule, 201);
    }

    public function update(Request $request, Condition $condition)
    {
        $data = $request->validate([
            'function_a_id' => 'required|exists:city_functions,id',
            'function_b_id' => 'required|exists:city_functions,id',
            'type' => 'required|in:forbidden,allowed,bonus,penalty',
            'value' => 'nullable|integer|min:-5|max:5'
        ]);

        Condition::validateRule($data);

        $condition->update($data);

        return response()->json($condition);
    }

    public function destroy(Condition $condition)
    {
        $condition->delete();
        return response()->json(['message' => 'Rule deleted']);
    }
}
