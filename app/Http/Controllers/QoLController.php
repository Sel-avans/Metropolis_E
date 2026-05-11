<?php

namespace App\Http\Controllers;

use App\Models\GridCell;
use App\Models\Condition;

class QoLController extends Controller
{
    public function details()
    {
        $cells = GridCell::with('function.effects')->get();
        $conditions = Condition::with(['functionA', 'functionB'])->get();

        $categories = [
            'safety'      => [],
            'recreation'  => [],
            'environment' => [],
            'amenities'   => [],
            'mobility'    => []
        ];

        $totals = [
            'safety'      => 0,
            'recreation'  => 0,
            'environment' => 0,
            'amenities'   => 0,
            'mobility'    => 0
        ];

        // ---------------------------------------------------------
        // 1. BASE EFFECTS
        // ---------------------------------------------------------
        foreach ($cells as $cell) {
            if (!$cell->function) continue;

            foreach ($cell->function->effects as $effect) {
                $categories[$effect->category][] = [
                    'function' => $cell->function->name,
                    'value'    => $effect->value
                ];

                $totals[$effect->category] += $effect->value;
            }
        }

        // ---------------------------------------------------------
        // 2. ADJACENCY EFFECTS
        // ---------------------------------------------------------
        $processedPairs = [];

        foreach ($cells as $cell) {
            if (!$cell->function) continue;

            $funcA = $cell->function;
            $neighbors = $this->getNeighbors($cell, $cells);

            foreach ($neighbors as $neighbor) {
                if (!$neighbor->function) continue;

                $funcB = $neighbor->function;

                // Avoid double counting
                $pairKey = min($funcA->id, $funcB->id) . '-' . max($funcA->id, $funcB->id);
                if (isset($processedPairs[$pairKey])) continue;

                $category = $funcA->category;

                // ---------------------------------------------------------
                // 2A. SAME CATEGORY BONUS (+2)
                // ---------------------------------------------------------
                if ($funcA->category === $funcB->category) {
                    $categories[$category][] = [
                        'function' => "{$funcA->name} next to {$funcB->name} (same category)",
                        'value'    => 2
                    ];

                    $totals[$category] += 2;
                }

                // ---------------------------------------------------------
                // 2B. CONDITION BONUS (only if not duplicate of same-category)
                // ---------------------------------------------------------
                $condition = $conditions
                    ->filter(fn($c) =>
                        ($c->function_a == $funcA->id && $c->function_b == $funcB->id) ||
                        ($c->function_a == $funcB->id && $c->function_b == $funcA->id)
                    )
                    ->sortByDesc('value')
                    ->first();

                if ($condition) {

                    // Skip duplicate +2 if it's same category
                    if (!($condition->value == 2 && $funcA->category === $funcB->category)) {

                        $value = $condition->value ?? 0;

                        $categories[$category][] = [
                            'function' => "{$funcA->name} next to {$funcB->name} ({$condition->type})",
                            'value'    => $value
                        ];

                        $totals[$category] += $value;
                    }
                }

                // ---------------------------------------------------------
                // 2C. SENSITIVE / POLLUTING PENALTIES
                // ---------------------------------------------------------
                $isSensitiveA = $funcA->sensitivity === 'sensitive';
                $isSensitiveB = $funcB->sensitivity === 'sensitive';

                $isPollutingA = $funcA->pollution === 'polluting';
                $isPollutingB = $funcB->pollution === 'polluting';

                if ($isSensitiveA && $isPollutingB) {
                    $categories[$funcA->category][] = [
                        'function' => "{$funcA->name} next to {$funcB->name} (penalty)",
                        'value'    => -2
                    ];
                    $totals[$funcA->category] -= 2;
                }

                if ($isSensitiveB && $isPollutingA) {
                    $categories[$funcB->category][] = [
                        'function' => "{$funcB->name} next to {$funcA->name} (penalty)",
                        'value'    => -2
                    ];
                    $totals[$funcB->category] -= 2;
                }

                $processedPairs[$pairKey] = true;
            }
        }

        // ---------------------------------------------------------
        // 3. RETURN RESULT
        // ---------------------------------------------------------
        return response()->json([
            'categories' => [
                'safety'      => ['total' => $totals['safety'],      'items' => $categories['safety']],
                'recreation'  => ['total' => $totals['recreation'],  'items' => $categories['recreation']],
                'environment' => ['total' => $totals['environment'], 'items' => $categories['environment']],
                'amenities'   => ['total' => $totals['amenities'],   'items' => $categories['amenities']],
                'mobility'    => ['total' => $totals['mobility'],    'items' => $categories['mobility']],
            ],
            'total_score' => array_sum($totals)
        ]);
    }

    private function getNeighbors($cell, $allCells)
    {
        $dirs = [
            [0, 1],
            [1, 0],
            [0, -1],
            [-1, 0],
        ];

        $neighbors = collect();

        foreach ($dirs as [$dr, $dc]) {
            $r = $cell->row + $dr;
            $c = $cell->col + $dc;

            $neighbor = $allCells->first(fn($gc) =>
                intval($gc->row) === intval($r) &&
                intval($gc->col) === intval($c)
            );

            if ($neighbor) {
                $neighbors->push($neighbor);
            }
        }

        return $neighbors;
    }

    public static function recalculateQoL()
{
    $controller = new self();
    $response = $controller->details()->getData(true);

    cache()->put('qol_data', $response, 60);

    return $response;
}

}
