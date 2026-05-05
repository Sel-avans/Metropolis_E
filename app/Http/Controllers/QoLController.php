<?php

namespace App\Http\Controllers;

use App\Models\GridCell;
use Illuminate\Http\Request;

class QoLController extends Controller
{
public function details()
{
    $cells = GridCell::with('function.effects')->get();

    $categories = [
        'veiligheid' => [],
        'recreatie' => [],
        'milieukwaliteit' => [],
        'voorzieningen' => [],
        'mobiliteit' => []
    ];

    $totals = [
        'veiligheid' => 0,
        'recreatie' => 0,
        'milieukwaliteit' => 0,
        'voorzieningen' => 0,
        'mobiliteit' => 0
    ];

    foreach ($cells as $cell) {
        if (!$cell->function) continue;

        foreach ($cell->function->effects as $effect) {
            $cat = $effect->category;
            $val = $effect->value;

            $categories[$cat][] = [
                'function' => $cell->function->name,
                'value' => $val
            ];

            $totals[$cat] += $val;
        }
    }

    return response()->json([
        'categories' => collect($categories)->map(fn($items, $cat) => [
            'total' => $totals[$cat],
            'items' => $items
        ]),
        'total_score' => array_sum($totals)
    ]);
}

}

