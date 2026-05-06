<?php

namespace App\Http\Controllers;

use App\Models\CityFunction;
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
        'name'      => 'required|string|max:255',
        'category'  => 'required|string|max:255',
        'icon'      => 'nullable|image|max:2048',
    ]);

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

    $categories = ['veiligheid', 'recreatie', 'voorzieningen', 'gezondheid', 'mobiliteit', 'milieu'];

    foreach ($categories as $cat) {
        \App\Models\Effect::create([
            'function_id' => $function->id,
            'category'    => $cat,
            'value'       => 0,
        ]);
    }

    return redirect()
        ->route('functions.index')
        ->with('status', 'Functie succesvol aangemaakt.');
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
            'name'      => 'required|string|max:255',
            'category'  => 'required|string|max:255',
            'icon'      => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('icon')) {
            if ($function->image && str_starts_with($function->image, 'storage/')) {
                $oldPath = str_replace('storage/', '', $function->image);
                Storage::disk('public')->delete($oldPath);
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
            ->with('status', 'Functie succesvol bijgewerkt.');
    }

    public function destroy(CityFunction $function)
    {
        if ($function->image && str_starts_with($function->image, 'storage/')) {
            $oldPath = str_replace('storage/', '', $function->image);
            Storage::disk('public')->delete($oldPath);
        }

        $function->delete();

        return redirect()
            ->route('functions.index')
            ->with('status', 'Functie succesvol verwijderd.');
    }
}
