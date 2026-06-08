<?php

namespace App\Http\Controllers;

use App\Models\CityFunction;
use App\Services\FunctionLibraryPreviewService;

class FunctionPreviewController extends Controller
{
    public function show(int $id, FunctionLibraryPreviewService $previewService)
    {
        $function = CityFunction::findOrFail($id);

        return response()->json($previewService->build($function));
    }
}
