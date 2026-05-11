<?php

namespace App\Http\Controllers;

use App\Models\GridCell;
use App\Models\AdjacencyRule;

class QoLController extends Controller
{
    public function details()
    {
        $cells = GridCell::with('function.effects')->get();
        
        // Fetch rules from the correct AdjacencyRule table
        $adjacencyRules = AdjacencyRule::all();

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

        // 1. Calculate base values from the buildings themselves
        foreach ($cells as $cell) {
            if (!$cell->function) continue;

            foreach ($cell->function->effects as $effect) {
                $categories[$effect->category][] = [
                    'function' => $cell->function->name,
                    'value' => $effect->value
                ];

                $totals[$effect->category] += $effect->value;
            }
        }

        $processedPairs = [];

        // 2. Calculate neighbor bonuses and penalties
        foreach ($cells as $cell) {
            if (!$cell->function) continue;

            $funcA = $cell->function;
            $neighbors = $this->getNeighbors($cell, $cells);

            foreach ($neighbors as $neighbor) {
                if (!$neighbor->function) continue;

                $funcB = $neighbor->function;

                // Create a unique key so we don't count A->B and B->A twice
                $pairKey = min($funcA->id, $funcB->id) . '-' . max($funcA->id, $funcB->id);

                if (isset($processedPairs[$pairKey])) {
                    continue;
                }

                // Check if a bonus or penalty rule exists in AdjacencyRules
                $rule = $adjacencyRules
                    ->filter(fn($r) =>
                        ($r->function_a == $funcA->id && $r->function_b == $funcB->id) ||
                        ($r->function_a == $funcB->id && $r->function_b == $funcA->id)
                    )
                    ->whereIn('type', ['bonus', 'penalty'])
                    ->sortByDesc('value')
                    ->first();

                $category = $funcA->id < $funcB->id ? $funcA->category : $funcB->category;

                // Apply the rule value if it exists
                if ($rule) {
                    $value = $rule->value ?? 0;

                    $categories[$category][] = [
                        'function' => "{$funcA->name} + {$funcB->name} ({$rule->type})",
                        'value'    => $value
                    ];

                    $totals[$category] += $value;
                }

                // Custom logic for sensitive/polluting buildings
                $isSensitiveA = $funcA->sensitivity === 'sensitive';
                $isSensitiveB = $funcB->sensitivity === 'sensitive';

                $isPollutingA = $funcA->pollution === 'polluting';
                $isPollutingB = $funcB->pollution === 'polluting';

                if ($isSensitiveA && $isPollutingB) {
                    $totals[$funcA->category] -= 2;

                    $categories[$funcA->category][] = [
                        'function' => "{$funcA->name} + {$funcB->name} (penalty)",
                        'value'    => -2
                    ];
                }

                if ($isSensitiveB && $isPollutingA) {
                    $totals[$funcB->category] -= 2;

                    $categories[$funcB->category][] = [
                        'function' => "{$funcB->name} + {$funcA->name} (penalty)",
                        'value'    => -2
                    ];
                }

                $processedPairs[$pairKey] = true;
            }
        }

        return response()->json([
            'categories' => [
                'veiligheid' => [
                    'total' => $totals['veiligheid'],
                    'items' => $categories['veiligheid']
                ],
                'recreatie' => [
                    'total' => $totals['recreatie'],
                    'items' => $categories['recreatie']
                ],
                'milieukwaliteit' => [
                    'total' => $totals['milieukwaliteit'],
                    'items' => $categories['milieukwaliteit']
                ],
                'voorzieningen' => [
                    'total' => $totals['voorzieningen'],
                    'items' => $categories['voorzieningen']
                ],
                'mobiliteit' => [
                    'total' => $totals['mobiliteit'],
                    'items' => $categories['mobiliteit']
                ],
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
}