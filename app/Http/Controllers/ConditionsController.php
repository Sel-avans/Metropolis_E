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

        $existsDifferentType = Condition::where('function_a', $request->function_a)
            ->where('function_b', $request->function_b)
            ->where('type', '!=', $request->type)
            ->exists();

        if ($existsDifferentType) {
            return back()
                ->with('_last_action', 'error')
                ->with('error', 'Je kunt deze combinatie niet opslaan omdat er al een regel bestaat met hetzelfde functie-paar maar een ander type.');
        }

        $existsSame = Condition::where('function_a', $request->function_a)
            ->where('function_b', $request->function_b)
            ->where('type', $request->type)
            ->exists();

        if ($existsSame) {
            return back()
                ->with('_last_action', 'error')
                ->with('error', 'Deze combinatie bestaat al.');
        }

        $existsReverse = Condition::where('function_a', $request->function_b)
            ->where('function_b', $request->function_a)
            ->exists();

        if ($existsReverse) {
            return back()
                ->with('_last_action', 'error')
                ->with('error', 'De omgekeerde combinatie bestaat al.');
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
            ->with('success', 'Regel succesvol toegevoegd.');
    }

public function update(Request $request, Condition $condition)
{
    $request->validate([
        'function_a' => 'required|exists:city_functions,id',
        'function_b' => 'required|exists:city_functions,id',
        'type'       => 'required|in:bonus,penalty,forbidden',
        'value'      => 'nullable|integer',
    ]);

    // Correcte no-changes check
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

    $condition->update([
        'function_a' => $request->function_a,
        'function_b' => $request->function_b,
        'type'       => $request->type,
        'value'      => $request->value,
    ]);

    return redirect()
        ->route('conditions.index')
        ->with('_last_action', 'write')
        ->with('success', 'Regel succesvol bijgewerkt.');
}

    public function destroy(Condition $condition)
    {
        $condition->delete();

        return redirect()
            ->back()
            ->with('_last_action', 'write')
            ->with('success', 'Regel verwijderd.');
    }
}
