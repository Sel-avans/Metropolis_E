<?php

namespace App\Http\Controllers;

use App\Models\Condition;
use App\Models\CityFunction;
use Illuminate\Http\Request;

class ConditionsController extends Controller
{   
    
    public function index()
    {
        if (!session()->has('_last_action') || session('_last_action') !== 'error') {
        session()->forget('error');
        session()->forget('edit_id');
        }
        $bonuses = Condition::with(['functionA', 'functionB'])
            ->where('type', 'bonus')
            ->get()
            ->sortBy([
                fn($a, $b) => strcmp($a->functionA->name, $b->functionA->name),
                fn($a, $b) => strcmp($a->functionB->name, $b->functionB->name),
            ])
            ->values();

        $penalties = Condition::with(['functionA', 'functionB'])
            ->where('type', 'penalty')
            ->get()
            ->sortBy([
                fn($a, $b) => strcmp($a->functionA->name, $b->functionA->name),
                fn($a, $b) => strcmp($a->functionB->name, $b->functionB->name),
            ])
            ->values();

        $forbidden = Condition::with(['functionA', 'functionB'])
            ->where('type', 'forbidden')
            ->get()
            ->sortBy([
                fn($a, $b) => strcmp($a->functionA->name, $b->functionA->name),
                fn($a, $b) => strcmp($a->functionB->name, $b->functionB->name),
            ])
            ->values();

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
        'value'      => 'nullable|integer',
    ]);

    if ((int)$request->function_a === (int)$request->function_b) {
        return back()
            ->with('_last_action', 'error')
            ->with('error', 'Function A and Function B cannot be the same.');
    }

    $existsAnyType = Condition::where('function_a', $request->function_a)
        ->where('function_b', $request->function_b)
        ->exists();

    if ($existsAnyType) {
        return back()
            ->with('_last_action', 'error')
            ->with('error', 'This combination already exists.');
    }

    $existsReverse = Condition::where('function_a', $request->function_b)
        ->where('function_b', $request->function_a)
        ->exists();

    if ($existsReverse) {
        return back()
            ->with('_last_action', 'error')
            ->with('error', 'The opposite combination already exists.');
    }

    Condition::create([
        'function_a' => $request->function_a,
        'function_b' => $request->function_b,
        'type'       => $request->type,
        'value'      => $request->value,
    ]);

    return redirect()
        ->route('conditions.index')
        ->with('_last_action', 'write')
        ->with('success', 'New rule added.');
}

    public function update(Request $request, Condition $condition)
    {
        $request->validate([
            'function_a' => 'required|exists:city_functions,id',
            'function_b' => 'required|exists:city_functions,id',
            'type'       => 'required|in:bonus,penalty,forbidden',
            'value'      => 'nullable|integer',
        ]);

        // ❗ No changes
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

        // ❗ Prevent A–B → B–A
        if (
            (int)$condition->function_a === (int)$request->function_b &&
            (int)$condition->function_b === (int)$request->function_a
        ) {
            return back()
                ->with('_last_action', 'error')
                ->with('edit_id', $condition->id)
                ->with('error', 'You cannot reverse an existing combination (A = B to B = A).');
        }

        // ❗ Prevent same pair with different type
        $existsDifferentType = Condition::where('id', '!=', $condition->id)
            ->where('function_a', $request->function_a)
            ->where('function_b', $request->function_b)
            ->where('type', '!=', $request->type)
            ->exists();

        if ($existsDifferentType) {
            return back()
                ->with('_last_action', 'error')
                ->with('edit_id', $condition->id)
                ->with('error', 'This combination already exists with another type.');
        }

        // ❗ Prevent same pair with same type
        $existsSame = Condition::where('id', '!=', $condition->id)
            ->where('function_a', $request->function_a)
            ->where('function_b', $request->function_b)
            ->where('type', $request->type)
            ->exists();

        if ($existsSame) {
            return back()
                ->with('_last_action', 'error')
                ->with('edit_id', $condition->id)
                ->with('error', 'This combination already exists.');
        }

        // ✔ Update
        $condition->update([
            'function_a' => $request->function_a,
            'function_b' => $request->function_b,
            'type'       => $request->type,
            'value'      => $request->value,
        ]);

        return redirect()
            ->route('conditions.index')
            ->with('_last_action', 'write')
            ->with('success', 'Rule modified succesful');
    }

    public function destroy(Condition $condition)
    {
        $condition->delete();

        return redirect()
            ->back()
            ->with('_last_action', 'write')
            ->with('success', 'Rule deleted.');
    }
}
