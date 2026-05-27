<?php

namespace App\Http\Controllers;

use App\Models\CityFunction;
use Illuminate\Http\Request;

class FunctionController extends Controller
{
    /**
     * Show the function library grouped by category.
     */
    public function index()
    {
        $items = CityFunction::all();
        $functions = $items->groupBy('category');

        return view('library', [
            'functions' => $functions
        ]);
    }

    /**
     * Show the create form.
     */
    public function create()
    {
        $categories = CityFunction::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('functions.create', compact('categories'));
    }

    /**
     * Store a new function.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255|unique:city_functions,name',
            'category' => 'required|string|max:255',
            'icon'     => 'nullable|image|max:2048',
        ], [
            'name.unique' => 'This function already exists in the library',
        
        ]);

        $inputCategory = strtolower(trim($request->category));

        $normalizedCategory = CityFunction::select('category')
            ->whereRaw('LOWER(category) = ?', [$inputCategory])
            ->value('category');

        $finalCategory = $normalizedCategory ?? $request->category;
        $data['category'] = $finalCategory;

        if ($request->hasFile('icon')) {
            $filename = time() . '_' . $request->file('icon')->getClientOriginalName();
            $request->file('icon')->move(public_path('icons'), $filename);
            $data['image'] = 'icons/' . $filename;
        }

        CityFunction::create([
            'name'     => $data['name'],
            'category' => $data['category'],
            'image'    => $data['image'] ?? null,
        ]);

        return redirect()
            ->route('functions.index')
            ->with('status', 'Function successfully created.');
    }

    /**
     * Show the edit form.
     */
    public function edit(CityFunction $function)
    {
        $categories = CityFunction::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('functions.edit', compact('function', 'categories'));
    }

    /**
     * Update an existing function.
     */
    public function update(Request $request, CityFunction $function)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'icon'     => 'nullable|image|max:2048',
        ], [
            'name.unique' => 'This name is already in use by another function',
        ]);
        
        $inputCategory = strtolower(trim($request->category));

        $normalizedCategory = CityFunction::select('category')
            ->whereRaw('LOWER(category) = ?', [$inputCategory])
            ->value('category');

        $finalCategory = $normalizedCategory ?? $request->category;
        $data['category'] = $finalCategory;

        if ($request->hasFile('icon')) {
            $filename = time() . '_' . $request->file('icon')->getClientOriginalName();
            $request->file('icon')->move(public_path('icons'), $filename);
            $data['image'] = 'icons/' . $filename;
        }

        $function->update([
            'name'     => $data['name'],
            'category' => $data['category'],
            'image'    => $data['image'] ?? $function->image,
        ]);

        return redirect()
            ->route('functions.index')
            ->with('status', 'Function successfully updated.');
    }

    /**
     * Delete a function.
     */
    public function destroy(CityFunction $function)
    {
        if ($function->image && str_starts_with($function->image, 'icons/')) {
            @unlink(public_path($function->image));
        }

        $function->delete();

        return redirect()
            ->route('functions.index')
            ->with('status', 'Function successfully deleted.');
    }
}
