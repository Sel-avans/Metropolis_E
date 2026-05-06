<?php

namespace App\Http\Controllers;

use App\Models\GridCell;
use Illuminate\Http\Request;

class QoLController extends Controller
{
public function details()
{
    $cells = GridCell::with('function.effects')->get();

        $categoryList = [
        'veiligheid',
        'recreatie',
        'voorzieningen',
        'mobiliteit',
        'milieu'
    ];

    $categories = [];
    $totals = [];

    foreach ($categoryList as $cat) {
        $categories[$cat] = [];
        $totals[$cat] = 0;
    }

    foreach ($cells as $cell) {
        if (!$cell->function) continue;

        foreach ($cell->function->effects as $effect) {
            $cat = $effect->category;
            $val = $effect->value;

            if (!array_key_exists($cat, $categories)) continue;

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

