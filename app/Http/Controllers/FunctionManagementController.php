<?php

namespace App\Http\Controllers;

use App\Models\CityFunction;
use App\Models\Effect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FunctionManagementController extends Controller
{
    public function index()
    {
        $functions = CityFunction::orderBy('category')->orderBy('name')->get();

        $categories = CityFunction::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('functions.index', compact('functions', 'categories'));
    }

    public function create()
    {
        $categories = CityFunction::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('functions.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255|unique:city_functions,name',
            'category'  => 'required|string|max:255',
            'icon'      => 'nullable|image|max:2048',
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

        $function = CityFunction::create([
            'name'     => $data['name'],
            'category' => $data['category'],
            'image'    => $data['image'] ?? null,
        ]);

        $allCategories = ['safety', 'recreation', 'environment', 'amenities', 'mobility'];

        foreach ($allCategories as $cat) {
            Effect::create([
                'function_id' => $function->id,
                'category'    => $cat,
                'value'       => 0,
            ]);
        }

        return redirect()
            ->route('functions.index')
            ->with('status', 'Function successfully created.');
    }

    public function edit(CityFunction $function)
    {
        $categories = CityFunction::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('functions.edit', compact('function', 'categories'));
    }

    public function update(Request $request, CityFunction $function)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255|unique:city_functions,name,' . $function->id,
            'category'  => 'required|string|max:255',
            'icon'      => 'nullable|image|max:2048',
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
            if ($function->image && str_starts_with($function->image, 'icons/')) {
                @unlink(public_path($function->image));
            }

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
