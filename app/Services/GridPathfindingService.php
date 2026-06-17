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

    /** @var list<int>|null */
    private ?array $routeForbiddenFunctionIds = null;

    /**
     * @return array{valid: bool, error?: string, message?: string}
     */
    public function validateEventEndpoint(int $row, int $col, int $functionId): array
    {
        return $this->validateRoutePathCell($row, $col, $functionId);
    }

    /**
     * Validate a route step from one cell to the next (draw mode).
     *
     * @return array{valid: bool, error?: string, message?: string}
     */
    public function validateRoutePathStep(
        int $fromRow,
        int $fromCol,
        int $toRow,
        int $toCol,
        ?int $expectedFunctionId = null
    ): array {
        $this->loadGrid();

        if (!$this->cellsAreAdjacent($fromRow, $fromCol, $toRow, $toCol)) {
            return [
                'valid' => false,
                'error' => 'invalid_path',
                'message' => 'Each step of the drawn route must be on an adjacent grid cell.',
            ];
        }

        $cellValidation = $this->validateRoutePathCell($toRow, $toCol, $expectedFunctionId);
        if (!$cellValidation['valid']) {
            return $cellValidation;
        }

        return ['valid' => true];
    }

    /**
     * @return array{valid: bool, error?: string, message?: string}
     */
    public function validateRoutePathCell(int $row, int $col, ?int $expectedFunctionId = null): array
    {
        $cellValidation = $this->validateRouteCellBasics($row, $col, $expectedFunctionId);
        if (!$cellValidation['valid']) {
            return $cellValidation;
        }

        if ($this->routePassesThroughForbiddenFunction($row, $col)) {
            return $this->forbiddenOnRouteCellFailure();
        }

        return ['valid' => true];
    }

    /**
     * @return array{valid: bool, error?: string, message?: string}
     */
    public function validateOccupiedCellForRoute(int $row, int $col, ?int $expectedFunctionId = null): array
    {
        $this->loadGrid();

        return $this->validateRoutePathCell($row, $col, $expectedFunctionId);
    }

    /**
     * @return array{valid: bool, error?: string, message?: string}
     */
    private function validateRouteCellBasics(int $row, int $col, ?int $expectedFunctionId = null): array
    {
        $functionId = $this->functionAt($row, $col);
        if ($functionId === null) {
            return [
                'valid' => false,
                'error' => 'empty_cell',
                'message' => 'The route may only pass through grid cells that contain a city function.',
            ];
        }

        if ($expectedFunctionId !== null && (int) $functionId !== (int) $expectedFunctionId) {
            return [
                'valid' => false,
                'error' => 'invalid_endpoint_cell',
                'message' => 'The event location is not on the selected grid cell.',
            ];
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

        $startValidation = $this->validateRoutePathCell($startRow, $startCol);
        if (!$startValidation['valid']) {
            return [
                'success' => false,
                'error' => $startValidation['error'] ?? 'invalid_event_location',
                'message' => $startValidation['message'] ?? 'The route start point is invalid.',
            ];
        }

        if ($startRow === $endRow && $startCol === $endCol) {
            return [
                'success' => false,
                'error' => 'invalid_route',
                'message' => 'The start point and event location cannot be the same cell.',
            ];
        }

        $path = $this->runAStar($startRow, $startCol, $endRow, $endCol, $endFunctionId);

        if ($path === null) {
            $pathIgnoringForbidden = $this->runAStar(
                $startRow,
                $startCol,
                $endRow,
                $endCol,
                $endFunctionId,
                enforceForbiddenRules: false
            );

            if ($pathIgnoringForbidden !== null) {
                return [
                    'success' => false,
                    'error' => 'forbidden_on_route',
                    'message' => 'No visitor route can be created through occupied grid cells between the start and end points.',
                ];
            }

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

            $cellValidation = $this->validateRoutePathCell(
                $cell['row'],
                $cell['col'],
                $isEnd ? $endFunctionId : null
            );

            if (!$cellValidation['valid']) {
                return [
                    'success' => false,
                    'error' => $cellValidation['error'] ?? 'invalid_path',
                    'message' => $cellValidation['message'] ?? 'The drawn route is invalid.',
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

        $this->routeForbiddenFunctionIds = null;
    }

    /**
     * Functions that may not appear on a visitor route when the route passes through them.
     *
     * @return list<int>
     */
    private function routeForbiddenFunctionIds(): array
    {
        if ($this->routeForbiddenFunctionIds !== null) {
            return $this->routeForbiddenFunctionIds;
        }

        $ids = [];

        foreach (AdjacencyRule::query()->where('type', 'forbidden')->get(['function_a', 'function_b']) as $rule) {
            $ids[] = (int) $rule->function_a;
            $ids[] = (int) $rule->function_b;
        }

        $this->routeForbiddenFunctionIds = array_values(array_unique($ids));

        return $this->routeForbiddenFunctionIds;
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

    private function routePassesThroughForbiddenFunction(int $row, int $col): bool
    {
        $functionId = $this->functionAt($row, $col);

        return $functionId !== null
            && in_array($functionId, $this->routeForbiddenFunctionIds(), true);
    }

    /**
     * @return array{valid: false, error: string, message: string}
     */
    private function forbiddenOnRouteCellFailure(): array
    {
        return [
            'valid' => false,
            'error' => 'forbidden_on_route',
            'message' => 'The route cannot pass through this forbidden function.',
        ];
    }

    private function canStepOnRoute(
        int $fromRow,
        int $fromCol,
        int $toRow,
        int $toCol,
        int $endRow,
        int $endCol,
        ?int $endFunctionId,
        bool $enforceForbiddenRules = true
    ): bool {
        $toFunctionId = $this->functionAt($toRow, $toCol);

        if ($toFunctionId === null) {
            return $toRow === $endRow && $toCol === $endCol;
        }

        if (
            $toRow === $endRow
            && $toCol === $endCol
            && $endFunctionId !== null
            && (int) $toFunctionId !== (int) $endFunctionId
        ) {
            return false;
        }

        if (!$enforceForbiddenRules) {
            return true;
        }

        if ($this->routePassesThroughForbiddenFunction($toRow, $toCol)) {
            return false;
        }

        return true;
    }

    private function cellsAreAdjacent(int $rowA, int $colA, int $rowB, int $colB): bool
    {
        return (abs($rowA - $rowB) + abs($colA - $colB)) === 1;
    }

    /**
     * @return list<array{row: int, col: int}>|null
     */
    private function runAStar(
        int $startRow,
        int $startCol,
        int $endRow,
        int $endCol,
        ?int $endFunctionId = null,
        bool $enforceForbiddenRules = true
    ): ?array {
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
                if (!$this->canStepOnRoute(
                    $currentRow,
                    $currentCol,
                    $neighborRow,
                    $neighborCol,
                    $endRow,
                    $endCol,
                    $endFunctionId,
                    $enforceForbiddenRules
                )) {
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
        return 'No route could be found from the access road to the event location through occupied grid cells.';
    }
}
