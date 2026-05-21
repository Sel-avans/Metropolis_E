<?php

namespace App\Http\Controllers;

use App\Models\Condition;
use App\Models\CityFunction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache; // Zorgt dat Cache::forget vlekkeloos werkt

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
            'value'      => 'nullable|integer|min:-5|max:5',
        ]);

        if ($request->type === 'penalty') {
            $request->merge(['value' => -abs($request->value)]);
        } else {
            $request->merge(['value' => abs($request->value)]);
        }

        if ($request->function_a == $request->function_b) {
            return back()
                ->with('_last_action', 'error')
                ->with('error', 'Function A and Function B cannot be the same.')
                ->with('edit_id', null)
                ->with('pending_data', $request->all());
        }

        $existsSame = Condition::where('function_a', $request->function_a)
            ->where('function_b', $request->function_b)
            ->where('type', $request->type)
            ->exists();

        if ($existsSame) {
            return back()
                ->with('_last_action', 'error')
                ->with('edit_id', null)
                ->with('error', 'This rule already exists.')
                ->with('pending_data', $request->all());
        }

        $existsDifferentType = Condition::where('function_a', $request->function_a)
            ->where('function_b', $request->function_b)
            ->where('type', '!=', $request->type)
            ->exists();

        if ($existsDifferentType) {
            return back()
                ->with('_last_action', 'error')
                ->with('edit_id', null)
                ->with('error', 'A rule with this function pair already exists with a different type.')
                ->with('pending_data', $request->all());
        }

        $existsReverse = Condition::where('function_a', $request->function_b)
            ->where('function_b', $request->function_a)
            ->exists();

        if ($existsReverse) {
            return back()
                ->with('_last_action', 'error')
                ->with('edit_id', null)
                ->with('error', 'The reversed combination already exists.')
                ->with('pending_data', $request->all());
        }

        Condition::create([
            'function_a' => $request->function_a,
            'function_b' => $request->function_b,
            'type'       => $request->type,
            'value'      => $request->value,
        ]);

        Cache::forget('qol_data');

        return redirect()
            ->route('conditions.index')
            ->with('success', 'Rule successfully created.');
    }

    public function update(Request $request, Condition $condition)
    {
        // Check of er daadwerkelijk iets gewijzigd is
        $noChanges =
            (int)$condition->function_a === (int)$request->function_a &&
            (int)$condition->function_b === (int)$request->function_b &&
            (string)$condition->type === (string)$request->type &&
            (string)($condition->value ?? '') === (string)($request->value ?? '');

        if ($noChanges) {
            return redirect()->route('conditions.index');
        }

        $request->validate([
            'function_a' => 'required|exists:city_functions,id',
            'function_b' => 'required|exists:city_functions,id',
            'type'       => 'required|in:bonus,penalty,forbidden',
            'value'      => 'nullable|integer|min:-5|max:5',
        ]);

        if ($request->type === 'penalty') {
            $request->merge(['value' => -abs($request->value)]);
        } else {
            $request->merge(['value' => abs($request->value)]);
        }

        if ($request->function_a == $request->function_b) {
            return back()
                ->with('_last_action', 'error')
                ->with('edit_id', $condition->id)
                ->with('error', 'Function A and Function B cannot be the same.')
                ->with('pending_data', $request->all());
        }

        // Voer de update direct uit
        $condition->update([
            'function_a' => $request->function_a,
            'function_b' => $request->function_b,
            'type'       => $request->type,
            'value'      => $request->value,
        ]);

        // Belangrijk: Gooi de grid cache leeg!
        Cache::forget('qol_data');

        return redirect()
            ->route('conditions.index')
            ->with('success', 'Rule successfully updated.');
    }

    public function destroy(Condition $condition)
    {
        $condition->delete();
        Cache::forget('qol_data');

        return back()->with('success', 'Rule deleted successfully.');
    }
}