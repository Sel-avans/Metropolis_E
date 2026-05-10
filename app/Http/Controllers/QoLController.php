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
                $categories[$effect->category][] = [
                    'function' => $cell->function->name,
                    'value' => $effect->value
                ];

                $totals[$effect->category] += $effect->value;
            }
        }

        $processedPairs = [];

        foreach ($cells as $cell) {
            if (!$cell->function) continue;

            $funcA = $cell->function;
            $neighbors = $this->getNeighbors($cell, $cells);

            foreach ($neighbors as $neighbor) {
                if (!$neighbor->function) continue;

                $funcB = $neighbor->function;

                $pairKey = min($funcA->id, $funcB->id) . '-' . max($funcA->id, $funcB->id);

                if (isset($processedPairs[$pairKey])) {
                    continue;
                }

        $condition = $conditions
            ->filter(fn($c) =>
                ($c->function_a == $funcA->id && $c->function_b == $funcB->id) ||
                ($c->function_a == $funcB->id && $c->function_b == $funcA->id)
            )
            ->sortByDesc('value')
            ->first();

            $category = $funcA->id < $funcB->id ? $funcA->category : $funcB->category;

            if ($condition) {
                $value = $condition->value ?? 0;

                $categories[$category][] = [
                    'function' => "{$funcA->name} naast {$funcB->name} ({$condition->type})",
                    'value'    => $value
                ];

                $totals[$category] += $value;
            }


                $isSensitiveA = $funcA->sensitivity === 'sensitive';
                $isSensitiveB = $funcB->sensitivity === 'sensitive';

                $isPollutingA = $funcA->pollution === 'polluting';
                $isPollutingB = $funcB->pollution === 'polluting';

                if ($isSensitiveA && $isPollutingB) {
                    $totals[$funcA->category] -= 2;

                    $categories[$funcA->category][] = [
                        'function' => "{$funcA->name} naast {$funcB->name} (penalty)",
                        'value'    => -2
                    ];
                }

                if ($isSensitiveB && $isPollutingA) {
                    $totals[$funcB->category] -= 2;

                    $categories[$funcB->category][] = [
                        'function' => "{$funcB->name} naast {$funcA->name} (penalty)",
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
