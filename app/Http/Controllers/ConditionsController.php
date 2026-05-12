<?php

namespace App\Http\Controllers;

use App\Models\Condition;
use App\Models\CityFunction;
use Illuminate\Http\Request;

class ConditionsController extends Controller
{
    public function index()
    {
        $bonuses = Condition::with(['functionA', 'functionB'])
            ->where('type', 'bonus')
            ->orderBy('id')
            ->get();

        $penalties = Condition::with(['functionA', 'functionB'])
            ->where('type', 'penalty')
            ->orderBy('id')
            ->get();

        $forbidden = Condition::with(['functionA', 'functionB'])
            ->where('type', 'forbidden')
            ->orderBy('id')
            ->get();

        $conditions = $bonuses
            ->concat($penalties)
            ->concat($forbidden)
            ->values();

        $functions = CityFunction::all();

        return view('conditions.index', compact('conditions', 'functions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'function_a' => 'required|exists:city_functions,id',
            'function_b' => 'required|exists:city_functions,id',
            'type'       => 'required|in:bonus,penalty,forbidden',
            'value'      => 'nullable|integer|min:-5|max:5',
        ]);

        if ($request->function_a == $request->function_b) {
            return back()
                ->with('_last_action', 'error')
                ->with('error', 'Function A and Function B cannot be the same.')
                ->with('edit_id', null);

        }

        $existsSame = Condition::where('function_a', $request->function_a)
            ->where('function_b', $request->function_b)
            ->where('type', $request->type)
            ->exists();

        if ($existsSame) {
            return back()
                ->with('_last_action', 'error')
                ->with('edit_id', null)
                ->with('error', 'This rule already exists.');
        }

        $existsDifferentType = Condition::where('function_a', $request->function_a)
            ->where('function_b', $request->function_b)
            ->where('type', '!=', $request->type)
            ->exists();

        if ($existsDifferentType) {
            return back()
                ->with('_last_action', 'error')
                ->with('edit_id', null)
                ->with('error', 'A rule with this function pair already exists with a different type.');
        }

        $existsReverse = Condition::where('function_a', $request->function_b)
            ->where('function_b', $request->function_a)
            ->exists();

        if ($existsReverse) {
            return back()
                ->with('_last_action', 'error')
                ->with('edit_id', null)
                ->with('error', 'The reversed combination already exists.');
        }

        Condition::create([
            'function_a' => $request->function_a,
            'function_b' => $request->function_b,
            'type'       => $request->type,
            'value'      => $request->value,
        ]);

        \App\Http\Controllers\QoLController::recalculateQoL();

        return redirect()
            ->route('conditions.index')
            ->with('_last_action', 'write')
            ->with('success', 'Rule successfully created.');
    }

public function update(Request $request, Condition $condition)
{
    // 1. NO-CHANGES CHECK (moet ALTIJD bovenaan!)
    $noChanges =
        (int)$condition->function_a === (int)$request->function_a &&
        (int)$condition->function_b === (int)$request->function_b &&
        (string)$condition->type === (string)$request->type &&
        (string)($condition->value ?? '') === (string)($request->value ?? '');

    if ($noChanges) {
        return redirect()
            ->route('conditions.index')
            ->with('_last_action', 'none');
    }

    // 2. VALIDATIE PAS HIER
    $request->validate([
        'function_a' => 'required|exists:city_functions,id',
        'function_b' => 'required|exists:city_functions,id',
        'type'       => 'required|in:bonus,penalty,forbidden',
        'value'      => 'nullable|integer|min:-5|max:5',
    ]);

    // 3. ALLE ANDERE CHECKS
    if ($request->function_a == $request->function_b) {
        return back()
            ->with('_last_action', 'error')
            ->with('edit_id', $condition->id)
            ->with('error', 'Function A and Function B cannot be the same.');
    }

    // Prevent turning A→B into B→A
    if (
        (int)$condition->function_a === (int)$request->function_b &&
        (int)$condition->function_b === (int)$request->function_a
    ) {
        return back()
            ->with('_last_action', 'error')
            ->with('edit_id', $condition->id)
            ->with('error', 'You cannot reverse the function order of an existing rule.');
    }

    $existsSame = Condition::where('id', '!=', $condition->id)
        ->where('function_a', $request->function_a)
        ->where('function_b', $request->function_b)
        ->where('type', $request->type)
        ->exists();

    if ($existsSame) {
        return back()
            ->with('_last_action', 'error')
            ->with('edit_id', $condition->id)
            ->with('error', 'This rule already exists.');
    }

    $existsDifferentType = Condition::where('id', '!=', $condition->id)
        ->where('function_a', $request->function_a)
        ->where('function_b', $request->function_b)
        ->where('type', '!=', $request->type)
        ->exists();

    if ($existsDifferentType) {
        return back()
            ->with('_last_action', 'error')
            ->with('edit_id', $condition->id)
            ->with('error', 'A rule with this function pair already exists with a different type.');
    }

    $existsReverse = Condition::where('id', '!=', $condition->id)
        ->where('function_a', $request->function_b)
        ->where('function_b', $request->function_a)
        ->exists();

    if ($existsReverse) {
        return back()
            ->with('_last_action', 'error')
            ->with('edit_id', $condition->id)
            ->with('error', 'The reversed combination already exists.');
    }

    // 4. UPDATE
    $condition->update([
        'function_a' => $request->function_a,
        'function_b' => $request->function_b,
        'type'       => $request->type,
        'value'      => $request->value,
    ]);

    return redirect()
        ->route('conditions.index')
        ->with('_last_action', 'write')
        ->with('success', 'Rule successfully updated.');
}
    public function destroy(Condition $condition)
    {
        $condition->delete();

        \App\Http\Controllers\QoLController::recalculateQoL();

        return redirect()
            ->back()
            ->with('_last_action', 'write')
            ->with('success', 'Rule deleted and QoL updated.');    
        }
}
