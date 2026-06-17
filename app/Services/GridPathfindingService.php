<?php

namespace App\Services;

use App\Models\AdjacencyRule;
use App\Models\CityFunction;
use App\Models\Condition;
use App\Models\GridCell;

class GridPathfindingService
{
    private const MIN_ROW = 1;

    private const MAX_ROW = 3;

    private const MIN_COL = 1;

    private const MAX_COL = 4;

    /** @var array<string, int|null> */
    private array $cellMap = [];

    /** @var list<int> */
    private array $roadFunctionIds = [];

    /**
     * @return array{valid: bool, error?: string, message?: string}
     */
    public function validateEventEndpoint(int $row, int $col, int $functionId): array
    {
        $this->loadGrid();

        if ((int) ($this->functionAt($row, $col) ?? 0) !== $functionId) {
            return [
                'valid' => false,
                'error' => 'invalid_endpoint_cell',
                'message' => 'The event location is not on the selected grid cell.',
            ];
        }

        foreach ($this->neighbors($row, $col) as [$neighborRow, $neighborCol]) {
            $neighborFunctionId = $this->functionAt($neighborRow, $neighborCol);
            if ($neighborFunctionId === null) {
                continue;
            }

            $type = $this->adjacencyType($functionId, $neighborFunctionId);
            if ($type === 'forbidden') {
                return [
                    'valid' => false,
                    'error' => 'invalid_event_location',
                    'message' => 'The event location is invalid because it has a forbidden neighbour combination.',
                ];
            }

            if ($type === 'penalty') {
                return [
                    'valid' => false,
                    'error' => 'invalid_event_location',
                    'message' => 'The event location is invalid because it has a penalty neighbour combination.',
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * @return array{
     *     success: bool,
     *     path?: list<array{row: int, col: int}>,
     *     error?: string,
     *     message?: string
     * }
     */
    public function findPath(
        int $startRow,
        int $startCol,
        int $endRow,
        int $endCol,
        int $endFunctionId
    ): array {
        $this->loadGrid();

        $endpointValidation = $this->validateEventEndpoint($endRow, $endCol, $endFunctionId);
        if (!$endpointValidation['valid']) {
            return [
                'success' => false,
                'error' => $endpointValidation['error'] ?? 'invalid_event_location',
                'message' => $endpointValidation['message'] ?? 'The event location is invalid.',
            ];
        }

        if (!$this->isRoadCell($startRow, $startCol)) {
            return [
                'success' => false,
                'error' => 'invalid_start_point',
                'message' => 'The route start point must be on a Road cell.',
            ];
        }

        if ($startRow === $endRow && $startCol === $endCol) {
            return [
                'success' => false,
                'error' => 'invalid_route',
                'message' => 'The start point and event location cannot be the same cell.',
            ];
        }

        $path = $this->runAStar($startRow, $startCol, $endRow, $endCol);

        if ($path === null) {
            return [
                'success' => false,
                'error' => 'unreachable_event_location',
                'message' => $this->unreachableMessage($startRow, $startCol, $endRow, $endCol),
            ];
        }

        return ['success' => true, 'path' => $path];
    }

    /**
     * @param list<array{row: int|string, col: int|string}> $pathCells
     * @return array{
     *     success: bool,
     *     path?: list<array{row: int, col: int}>,
     *     error?: string,
     *     message?: string
     * }
     */
    public function validateManualPath(
        int $startRow,
        int $startCol,
        int $endRow,
        int $endCol,
        int $endFunctionId,
        array $pathCells
    ): array {
        $this->loadGrid();

        $endpointValidation = $this->validateEventEndpoint($endRow, $endCol, $endFunctionId);
        if (!$endpointValidation['valid']) {
            return [
                'success' => false,
                'error' => $endpointValidation['error'] ?? 'invalid_event_location',
                'message' => $endpointValidation['message'] ?? 'The event location is invalid.',
            ];
        }

        if ($pathCells === []) {
            return [
                'success' => false,
                'error' => 'empty_path',
                'message' => 'Draw at least one route cell on the City Grid.',
            ];
        }

        $normalized = [];
        foreach ($pathCells as $cell) {
            if (!isset($cell['row'], $cell['col'])) {
                return [
                    'success' => false,
                    'error' => 'invalid_path',
                    'message' => 'The drawn route is invalid.',
                ];
            }

            $normalized[] = [
                'row' => (int) $cell['row'],
                'col' => (int) $cell['col'],
            ];
        }

        $first = $normalized[0];
        $last = $normalized[array_key_last($normalized)];

        if ($first['row'] !== $startRow || $first['col'] !== $startCol) {
            return [
                'success' => false,
                'error' => 'invalid_path',
                'message' => 'The drawn route must start at the access road start point.',
            ];
        }

        if ($last['row'] !== $endRow || $last['col'] !== $endCol) {
            return [
                'success' => false,
                'error' => 'invalid_path',
                'message' => 'The drawn route must end at the event location.',
            ];
        }

        for ($index = 0; $index < count($normalized); $index++) {
            $cell = $normalized[$index];
            $isEnd = $index === count($normalized) - 1;

            if (!$isEnd && $this->functionAt($cell['row'], $cell['col']) === null) {
                return [
                    'success' => false,
                    'error' => 'invalid_path',
                    'message' => 'The route may only pass through grid cells that contain a city function.',
                ];
            }

            if ($index > 0) {
                $previous = $normalized[$index - 1];
                if (!$this->cellsAreAdjacent($previous['row'], $previous['col'], $cell['row'], $cell['col'])) {
                    return [
                        'success' => false,
                        'error' => 'invalid_path',
                        'message' => 'Each step of the drawn route must be on an adjacent grid cell.',
                    ];
                }
            }
        }

        return ['success' => true, 'path' => $normalized];
    }

    private function loadGrid(): void
    {
        $this->cellMap = [];
        foreach (GridCell::all() as $cell) {
            $this->cellMap[$this->cellKey((int) $cell->row, (int) $cell->col)] = $cell->function_id
                ? (int) $cell->function_id
                : null;
        }

        $this->roadFunctionIds = CityFunction::query()
            ->whereRaw('LOWER(TRIM(name)) LIKE ?', ['%' . strtolower(EventRouteService::MAIN_ACCESS_ROAD_NAME) . '%'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function neighbors(int $row, int $col): array
    {
        $neighbors = [];
        $directions = [[0, 1], [1, 0], [0, -1], [-1, 0]];

        foreach ($directions as [$deltaRow, $deltaCol]) {
            $neighborRow = $row + $deltaRow;
            $neighborCol = $col + $deltaCol;

            if (
                $neighborRow >= self::MIN_ROW
                && $neighborRow <= self::MAX_ROW
                && $neighborCol >= self::MIN_COL
                && $neighborCol <= self::MAX_COL
            ) {
                $neighbors[] = [$neighborRow, $neighborCol];
            }
        }

        return $neighbors;
    }

    private function cellKey(int $row, int $col): string
    {
        return $row . ':' . $col;
    }

    private function functionAt(int $row, int $col): ?int
    {
        return $this->cellMap[$this->cellKey($row, $col)] ?? null;
    }

    private function isRoadCell(int $row, int $col): bool
    {
        $functionId = $this->functionAt($row, $col);

        return $functionId !== null && in_array($functionId, $this->roadFunctionIds, true);
    }

    private function isTraversable(int $row, int $col, int $endRow, int $endCol): bool
    {
        // Only the start point must be on a Road. Routes may cross any occupied grid cell.
        return $this->functionAt($row, $col) !== null || ($row === $endRow && $col === $endCol);
    }

    private function cellsAreAdjacent(int $rowA, int $colA, int $rowB, int $colB): bool
    {
        return (abs($rowA - $rowB) + abs($colA - $colB)) === 1;
    }

    /**
     * @return list<array{row: int, col: int}>|null
     */
    private function runAStar(int $startRow, int $startCol, int $endRow, int $endCol): ?array
    {
        $startKey = $this->cellKey($startRow, $startCol);
        $endKey = $this->cellKey($endRow, $endCol);

        $openSet = [$startKey];
        $cameFrom = [];
        $gScore = [$startKey => 0];
        $fScore = [$startKey => $this->heuristic($startRow, $startCol, $endRow, $endCol)];

        while ($openSet !== []) {
            usort($openSet, fn (string $a, string $b) => ($fScore[$a] ?? PHP_INT_MAX) <=> ($fScore[$b] ?? PHP_INT_MAX));
            $currentKey = array_shift($openSet);

            if ($currentKey === $endKey) {
                return $this->reconstructPath($cameFrom, $currentKey);
            }

            [$currentRow, $currentCol] = array_map('intval', explode(':', $currentKey));

            foreach ($this->neighbors($currentRow, $currentCol) as [$neighborRow, $neighborCol]) {
                if (!$this->isTraversable($neighborRow, $neighborCol, $endRow, $endCol)) {
                    continue;
                }

                $neighborKey = $this->cellKey($neighborRow, $neighborCol);
                $tentativeGScore = ($gScore[$currentKey] ?? PHP_INT_MAX) + 1;

                if ($tentativeGScore >= ($gScore[$neighborKey] ?? PHP_INT_MAX)) {
                    continue;
                }

                $cameFrom[$neighborKey] = $currentKey;
                $gScore[$neighborKey] = $tentativeGScore;
                $fScore[$neighborKey] = $tentativeGScore + $this->heuristic(
                    $neighborRow,
                    $neighborCol,
                    $endRow,
                    $endCol
                );

                if (!in_array($neighborKey, $openSet, true)) {
                    $openSet[] = $neighborKey;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $cameFrom
     * @return list<array{row: int, col: int}>
     */
    private function reconstructPath(array $cameFrom, string $currentKey): array
    {
        $path = [];
        while (isset($cameFrom[$currentKey])) {
            [$row, $col] = array_map('intval', explode(':', $currentKey));
            array_unshift($path, ['row' => $row, 'col' => $col]);
            $currentKey = $cameFrom[$currentKey];
        }

        [$startRow, $startCol] = array_map('intval', explode(':', $currentKey));
        array_unshift($path, ['row' => $startRow, 'col' => $startCol]);

        return $path;
    }

    private function heuristic(int $row, int $col, int $endRow, int $endCol): int
    {
        return abs($row - $endRow) + abs($col - $endCol);
    }

    private function adjacencyType(int $functionIdA, int $functionIdB): ?string
    {
        if ($functionIdA === $functionIdB) {
            return null;
        }

        $functionA = min($functionIdA, $functionIdB);
        $functionB = max($functionIdA, $functionIdB);

        $rule = AdjacencyRule::query()
            ->where(function ($query) use ($functionA, $functionB) {
                $query->where('function_a', $functionA)->where('function_b', $functionB);
            })
            ->orWhere(function ($query) use ($functionA, $functionB) {
                $query->where('function_a', $functionB)->where('function_b', $functionA);
            })
            ->first();

        if ($rule) {
            return $rule->type;
        }

        $condition = Condition::query()
            ->where(function ($query) use ($functionA, $functionB) {
                $query->where(function ($inner) use ($functionA, $functionB) {
                    $inner->where('function_a', $functionA)->where('function_b', $functionB);
                })->orWhere(function ($inner) use ($functionA, $functionB) {
                    $inner->where('function_a', $functionB)->where('function_b', $functionA);
                });
            })
            ->where('type', 'forbidden')
            ->first();

        return $condition ? 'forbidden' : null;
    }

    private function unreachableMessage(int $startRow, int $startCol, int $endRow, int $endCol): string
    {
        return 'No route could be found from the access road to the event location.';
    }
}
