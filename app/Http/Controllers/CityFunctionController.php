<?php

namespace App\Http\Controllers;

use App\Models\CityFunction;
use Illuminate\Http\Request;

class CityFunctionController extends Controller
{
    public function index(Request $request)
    {
        $query = CityFunction::with('category');

        if ($request->category) {
            $query->where('category_id', $request->category);
        }

        $functions = $query->get();

        if ($functions->isEmpty()) {
            return response()->json([
                'message' => 'No functions available'
            ]);
        }

        return response()->json($functions);
    }
}
