<?php

namespace App\Http\Controllers;

use App\Models\GridCell;
use App\Models\Condition;
use App\Models\AdjacencyRule;
use App\Services\EventModifierService;

class QoLController extends Controller
{
    public function details()
    {
        $cells = GridCell::with('function.effects')->get();
        $occupiedCells = $cells->filter(fn ($cell) => $cell->function_id && $cell->function);

        if ($occupiedCells->isEmpty()) {
            return response()->json([
                'categories' => [
                    'Safety'      => ['total' => 0, 'items' => []],
                    'Recreation'  => ['total' => 0, 'items' => []],
                    'Environment' => ['total' => 0, 'items' => []],
                    'Amenities'   => ['total' => 0, 'items' => []],
                    'Mobility'    => ['total' => 0, 'items' => []],
                ],
                'total_score' => 0,
            ]);
        }

        $conditions = Condition::with(['functionA', 'functionB'])->get();

        $categories = [
            'safety'      => [],
            'recreation'  => [],
            'environment' => [],
            'amenities'   => [],
            'mobility'    => [],
        ];

        $totals = [
            'safety'      => 0,
            'recreation'  => 0,
            'environment' => 0,
            'amenities'   => 0,
            'mobility'    => 0,
        ];

        $functionBaseByCategory = array_fill_keys(array_keys($totals), 0);
        $discard = [];

        foreach ($occupiedCells as $cell) {
            $cellTotals = $this->computeCellTotals($cell, $cells, $conditions, $discard);

            foreach ($cellTotals as $catKey => $value) {
                $totals[$catKey] += $value;
            }

            foreach (EventModifierService::getModifiersByCategoryForFunction((int) $cell->function_id) as $catKey => $value) {
                if ($value != 0 && isset($totals[$catKey])) {
                    $totals[$catKey] += $value;
                }
            }
        }

        foreach ($occupiedCells as $cell) {
            foreach ($cell->function->effects as $effect) {
                $catKey = strtolower($effect->category);
                if (isset($functionBaseByCategory[$catKey])) {
                    $functionBaseByCategory[$catKey] += $effect->value;
                }
            }
        }

        foreach ($functionBaseByCategory as $catKey => $sum) {
            if ($sum === 0) {
                continue;
            }

            $categories[$catKey][] = [
                'function' => 'Functies',
                'value'    => $sum,
            ];
        }

        $eventBreakdownTotals = [];

        foreach ($occupiedCells as $cell) {
            foreach (EventModifierService::getModifierBreakdownForFunction((int) $cell->function_id) as $modifier) {
                $key = $modifier['event_name'] . '|' . $modifier['category'];
                $eventBreakdownTotals[$key] = [
                    'event_name' => $modifier['event_name'],
                    'category' => $modifier['category'],
                    'value' => ($eventBreakdownTotals[$key]['value'] ?? 0) + $modifier['value'],
                ];
            }
        }

        foreach ($eventBreakdownTotals as $modifier) {
            $catKey = $modifier['category'];
            $value = $modifier['value'];

            if ($value == 0 || !isset($categories[$catKey])) {
                continue;
            }

            $categories[$catKey][] = [
                'function' => "{$modifier['event_name']} (event)",
                'value'    => $value,
            ];
        }

        return response()->json([
            'categories' => [
                'Safety'      => ['total' => $totals['safety'],      'items' => $categories['safety']],
                'Recreation'  => ['total' => $totals['recreation'],  'items' => $categories['recreation']],
                'Environment' => ['total' => $totals['environment'], 'items' => $categories['environment']],
                'Amenities'   => ['total' => $totals['amenities'],   'items' => $categories['amenities']],
                'Mobility'    => ['total' => $totals['mobility'],    'items' => $categories['mobility']],
            ],
            'total_score' => array_sum($totals),
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

        $discard = [];
        $totals = $this->computeCellTotals($targetCell, $cells, $conditions, $discard);

        $eventModifiers = EventModifierService::getModifiersByCategoryForFunction((int) $targetCell->function_id);
        $perCellTotal = array_sum($totals) + array_sum($eventModifiers);

        return response()->json([
            'categories' => [
                'Safety'      => ['total' => $totals['safety']],
                'Recreation'  => ['total' => $totals['recreation']],
                'Environment' => ['total' => $totals['environment']],
                'Amenities'   => ['total' => $totals['amenities']],
                'Mobility'    => ['total' => $totals['mobility']],
            ],
            'event_modifiers' => $eventModifiers,
            'total_score' => $perCellTotal,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, GridCell>  $cells
     * @param  array<string, array<int, array{function: string, value: int|float}|string>>  $items
     * @return array<string, int|float>
     */
    private function computeCellTotals($cell, $cells, $conditions, array &$items): array
    {
        $totals = [
            'safety'      => 0,
            'recreation'  => 0,
            'environment' => 0,
            'amenities'   => 0,
            'mobility'    => 0,
        ];

        if (!$cell->function) {
            return $totals;
        }

        $funcA = $cell->function;

        foreach ($funcA->effects as $effect) {
            $catKey = strtolower($effect->category);
            if (isset($totals[$catKey])) {
                $totals[$catKey] += $effect->value;
            }
        }

        foreach ($this->getNeighbors($cell, $cells) as $neighbor) {
            if (!$neighbor->function) {
                continue;
            }

            $isFirst =
                ($cell->row < $neighbor->row) ||
                ($cell->row == $neighbor->row && $cell->col < $neighbor->col);

            if (!$isFirst) {
                continue;
            }

            $this->applyAdjacencyBetween(
                $funcA,
                $neighbor->function,
                $conditions,
                $totals,
                $items,
                true
            );
        }

        return $totals;
    }

    /**
     * @param  array<string, int|float>  $totals
     * @param  array<string, array<int, array{function: string, value: int|float}|string>>  $items
     */
    private function applyAdjacencyBetween(
        $funcA,
        $funcB,
        $conditions,
        array &$totals,
        array &$items,
        bool $hoverStrings = false
    ): void {
        $catA = strtolower($funcA->category);
        $catB = strtolower($funcB->category);

        $conditionExists = $conditions->first(function ($c) use ($funcA, $funcB) {
            return $c->function_a == min($funcA->id, $funcB->id)
                && $c->function_b == max($funcA->id, $funcB->id);
        });

        if (!$conditionExists && $catA === $catB && isset($totals[$catA])) {
            $totals[$catA] += 2;
            $items[$catA][] = $hoverStrings
                ? "{$funcB->name} (Zelfde categorie: +2)"
                : [
                    'function' => "{$funcA->name} next to {$funcB->name} (same category)",
                    'value' => 2,
                ];
        }

        $condition = $conditions
            ->filter(fn ($c) =>
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
                $totals[$chosenCat] += $value;
                $items[$chosenCat][] = $hoverStrings
                    ? "{$funcB->name} ({$condition->type})"
                    : [
                        'function' => "{$funcA->name} next to {$funcB->name} ({$condition->type})",
                        'value' => $value,
                    ];
            }
        }

        $isSensitiveA = $funcA->sensitivity === 'sensitive';
        $isSensitiveB = $funcB->sensitivity === 'sensitive';
        $isPollutingA = $funcA->pollution === 'polluting';
        $isPollutingB = $funcB->pollution === 'polluting';

        if ($isSensitiveA && $isPollutingB && isset($totals[$catA])) {
            $totals[$catA] -= 2;
            $items[$catA][] = $hoverStrings
                ? "Hinder van {$funcB->name}"
                : [
                    'function' => "{$funcA->name} next to {$funcB->name} (penalty)",
                    'value' => -2,
                ];
        }

        if ($isSensitiveB && $isPollutingA && isset($totals[$catB])) {
            $totals[$catB] -= 2;
            $items[$catB][] = $hoverStrings
                ? "Hinder bij {$funcB->name}"
                : [
                    'function' => "{$funcB->name} next to {$funcA->name} (penalty)",
                    'value' => -2,
                ];
        }
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