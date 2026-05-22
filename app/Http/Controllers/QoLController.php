<?php

namespace App\Http\Controllers;

use App\Models\GridCell;
use App\Models\Condition;

class QoLController extends Controller
{
    public function details()
    {
        $cells = GridCell::with('function.effects')->get();

        if ($cells->whereNotNull('function_id')->isEmpty()) {
            return response()->json([
                'categories' => [
                    'Safety'      => ['total' => 0, 'items' => []],
                    'Recreation'  => ['total' => 0, 'items' => []],
                    'Environment' => ['total' => 0, 'items' => []],
                    'Amenities'   => ['total' => 0, 'items' => []],
                    'Mobility'    => ['total' => 0, 'items' => []],
                ],
                'total_score' => 0
            ]);
        }
    
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

        foreach ($cells as $cell) {
            if (!$cell->function_id || !$cell->function) continue;

            foreach ($cell->function->effects as $effect) {
                $catKey = strtolower($effect->category);
                if (isset($totals[$catKey])) {
                    $categories[$catKey][] = [
                        'function' => $cell->function->name,
                        'value'    => $effect->value
                    ];
                    $totals[$catKey] += $effect->value;
                }
            }
        }

        foreach ($cells as $cell) {
            if (!$cell->function) continue;

            $funcA = $cell->function;
            $neighbors = $this->getNeighbors($cell, $cells);

            foreach ($neighbors as $neighbor) {
                if (!$neighbor->function) continue;

                $funcB = $neighbor->function;

                $isFirst =
                    ($cell->row < $neighbor->row) ||
                    ($cell->row == $neighbor->row && $cell->col < $neighbor->col);

                if (!$isFirst) {
                    continue;
                }

                $catA = strtolower($funcA->category);
                $catB = strtolower($funcB->category);

                $conditionExists = $conditions->first(function ($c) use ($funcA, $funcB) {
                    return $c->function_a == min($funcA->id, $funcB->id)
                        && $c->function_b == max($funcA->id, $funcB->id);
                });

                if (!$conditionExists && $catA === $catB) {
                    if (isset($totals[$catA])) {
                        $categories[$catA][] = [
                            'function' => "{$funcA->name} next to {$funcB->name} (same category)",
                            'value'    => 2
                        ];
                        $totals[$catA] += 2;
                    }
                }

                $condition = $conditions
                    ->filter(fn($c) =>
                        ($c->function_a == $funcA->id && $c->function_b == $funcB->id) ||
                        ($c->function_a == $funcB->id && $c->function_b == $funcA->id)
                    )
                    ->whereIn('type', ['bonus', 'penalty'])
                    ->sortByDesc('value')
                    ->first();

                if ($condition) {
                    $chosenCat = $funcA->id < $funcB->id ? $catA : $catB;
                    $value = $condition->value ?? 0;

                    if (isset($totals[$chosenCat])) {
                        $categories[$chosenCat][] = [
                            'function' => "{$funcA->name} next to {$funcB->name} ({$condition->type})",
                            'value'    => $value
                        ];
                        $totals[$chosenCat] += $value;
                    }
                }

                $isSensitiveA = $funcA->sensitivity === 'sensitive';
                $isSensitiveB = $funcB->sensitivity === 'sensitive';

                $isPollutingA = $funcA->pollution === 'polluting';
                $isPollutingB = $funcB->pollution === 'polluting';

                if ($isSensitiveA && $isPollutingB) {
                    if (isset($totals[$catA])) {
                        $categories[$catA][] = [
                            'function' => "{$funcA->name} next to {$funcB->name} (penalty)",
                            'value'    => -2
                        ];
                        $totals[$catA] -= 2;
                    }
                }

                if ($isSensitiveB && $isPollutingA) {
                    if (isset($totals[$catB])) {
                        $categories[$catB][] = [
                            'function' => "{$funcB->name} next to {$funcA->name} (penalty)",
                            'value'    => -2
                        ];
                        $totals[$catB] -= 2;
                    }
                }
            }
        }

        return response()->json([
            'categories' => [
                'Safety'      => ['total' => $totals['safety'],      'items' => $categories['safety']],
                'Recreation'  => ['total' => $totals['recreation'],  'items' => $categories['recreation']],
                'Environment' => ['total' => $totals['environment'], 'items' => $categories['environment']],
                'Amenities'   => ['total' => $totals['amenities'],   'items' => $categories['amenities']],
                'Mobility'    => ['total' => $totals['mobility'],    'items' => $categories['mobility']],
            ],
            'total_score' => array_sum($totals)
        ]);
    }

    public function cellHoverDetails($row, $col)
    {
        $cells = GridCell::with('function.effects')->get();
        $conditions = Condition::with(['functionA', 'functionB'])->get();

        $targetCell = $cells->first(fn($gc) => intval($gc->row) === intval($row) && intval($gc->col) === intval($col));

        if (!$targetCell || !$targetCell->function) {
            return response()->json(['categories' => [], 'total_score' => 0]);
        }

        $funcA = $targetCell->function;
        $neighbors = $this->getNeighbors($targetCell, $cells);

        $totals = [
            'safety'      => 0,
            'recreation'  => 0,
            'environment' => 0,
            'amenities'   => 0,
            'mobility'    => 0
        ];
        
        $breakdown = [];

        foreach ($funcA->effects as $effect) {
            $catKey = strtolower($effect->category);
            if (isset($totals[$catKey])) {
                $totals[$catKey] += $effect->value;
                $breakdown[$catKey][] = "Basiswaarde: {$effect->value}";
            }
        }

        foreach ($neighbors as $neighbor) {
            if (!$neighbor->function) continue;

            $funcB = $neighbor->function;
            $catA = strtolower($funcA->category);
            $catB = strtolower($funcB->category);

            if ($catA === $catB) {
                if (isset($totals[$catA])) {
                    $totals[$catA] += 2;
                    $breakdown[$catA][] = "{$funcB->name} (Zelfde categorie: +2)";
                }
            }

            $condition = $conditions->filter(fn($c) =>
                ($c->function_a == $funcA->id && $c->function_b == $funcB->id) ||
                ($c->function_a == $funcB->id && $c->function_b == $funcA->id)
            )->whereIn('type', ['bonus', 'penalty'])->sortByDesc('value')->first();

            if ($condition) {
                $chosenCat = $funcA->id < $funcB->id ? $catA : $catB;
                $value = $condition->value ?? 0;
                if (isset($totals[$chosenCat])) {
                    $totals[$chosenCat] += $value;
                    $breakdown[$chosenCat][] = "{$funcB->name} ({$condition->type})";
                }
            }

            $isSensitiveA = $funcA->sensitivity === 'sensitive';
            $isSensitiveB = $funcB->sensitivity === 'sensitive';
            $isPollutingA = $funcA->pollution === 'polluting';
            $isPollutingB = $funcB->pollution === 'polluting';

            if ($isSensitiveA && $isPollutingB) {
                if (isset($totals[$catA])) {
                    $totals[$catA] -= 2;
                    $breakdown[$catA][] = "Hinder van {$funcB->name}";
                }
            }
            if ($isSensitiveB && $isPollutingA) {
                if (isset($totals[$catB])) {
                    $totals[$catB] -= 2;
                    $breakdown[$catB][] = "Hinder bij {$funcB->name}";
                }
            }
        }

        $categoryMapping = [
            'safety'      => 'Safety',
            'recreation'  => 'Recreation',
            'environment' => 'Environment',
            'amenities'   => 'Amenities',
            'mobility'    => 'Mobility',
        ];

        $formattedCategories = [];
        foreach ($totals as $key => $total) {
            if ($total !== 0 || !empty($breakdown[$key])) {
                $formattedCategories[$categoryMapping[$key]] = [
                    'total' => $total,
                    'items' => $breakdown[$key] ?? []
                ];
            }
        }

        return response()->json([
            'categories'  => $formattedCategories,
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