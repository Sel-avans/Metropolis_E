<?php

namespace App\Services;

use App\Models\CityFunction;
use App\Models\EventRoute;
use App\Models\GridCell;
use App\Models\SimulationEvent;

class EventRouteService
{
    public const MAIN_ACCESS_ROAD_NAME = 'Road';

    public function __construct(private GridPathfindingService $pathfindingService)
    {
    }

    public function isMainAccessRoadCell(GridCell $cell): bool
    {
        if (!$cell->function_id) {
            return false;
        }

        $cell->loadMissing('function');

        if (!$cell->function) {
            return false;
        }

        // Be tolerant with function naming (e.g. "Main Road", "Road 1", etc.).
        // We only need a "road"-like function for start point selection.
        $name = strtolower(trim($cell->function->name ?? ''));
        $road = strtolower(self::MAIN_ACCESS_ROAD_NAME);

        return str_contains($name, $road);
    }

    public function findMainAccessRoadCell(int $row, int $col): ?GridCell
    {
        $cell = GridCell::with('function')
            ->where('row', $row)
            ->where('col', $col)
            ->first();

        if (!$cell || !$this->isMainAccessRoadCell($cell)) {
            return null;
        }

        return $cell;
    }

    /**
     * @return array{success: bool, route?: EventRoute, error?: string, message?: string}
     */
    public function setStartPoint(SimulationEvent $event, int $row, int $col): array
    {
        $cell = $this->findMainAccessRoadCell($row, $col);

        if (!$cell) {
            return [
                'success' => false,
                'error' => 'invalid_start_point',
                'message' => 'This cell cannot be the start point. Select a cell that contains a Road function.',
            ];
        }

        $route = EventRoute::updateOrCreate(
            ['simulation_event_id' => $event->id],
            [
                'start_row' => $row,
                'start_col' => $col,
                'end_row' => null,
                'end_col' => null,
                'end_function_id' => null,
                'path_cells' => null,
            ]
        );

        return ['success' => true, 'route' => $route->fresh()];
    }

    /**
     * @return array{
     *     success: bool,
     *     assigned_functions?: list<array{
     *         id: int,
     *         name: string,
     *         placements: list<array{row: int, col: int}>
     *     }>,
     *     route?: EventRoute,
     *     error?: string,
     *     message?: string
     * }
     */
    public function getEndpointContext(SimulationEvent $event): array
    {
        $route = EventRoute::where('simulation_event_id', $event->id)->first();

        if (!$route || $route->start_row === null || $route->start_col === null) {
            return [
                'success' => false,
                'error' => 'missing_start_point',
                'message' => 'Set a start point on a Road cell before setting an end point.',
            ];
        }

        $assignedFunctions = $this->assignedFunctionsWithPlacements($event);

        if ($assignedFunctions === []) {
            return [
                'success' => false,
                'error' => 'no_assigned_functions',
                'message' => 'This event has no assigned city functions to use as an end point.',
            ];
        }

        return [
            'success' => true,
            'assigned_functions' => $assignedFunctions,
            'route' => $route->fresh()->load('endFunction'),
        ];
    }

    /**
     * @return array{success: bool, route?: EventRoute, error?: string, message?: string}
     */
    public function setEndpoint(SimulationEvent $event, int $functionId, ?int $row = null, ?int $col = null): array
    {
        $context = $this->getEndpointContext($event);

        if (!$context['success']) {
            return $context;
        }

        /** @var EventRoute $route */
        $route = $context['route'];
        $assignedFunctions = $context['assigned_functions'];

        $functionEntry = collect($assignedFunctions)->firstWhere('id', $functionId);

        if (!$functionEntry) {
            return [
                'success' => false,
                'error' => 'invalid_function',
                'message' => 'This function is not assigned to the selected event.',
            ];
        }

        $placements = $functionEntry['placements'];

        if ($placements === []) {
            return [
                'success' => false,
                'error' => 'function_not_on_grid',
                'message' => "Place {$functionEntry['name']} on the City Grid before setting the end point.",
            ];
        }

        if (count($placements) === 1) {
            $placement = $placements[0];
            $row = (int) $placement['row'];
            $col = (int) $placement['col'];
        } elseif ($row === null || $col === null) {
            return [
                'success' => false,
                'error' => 'endpoint_choice_required',
                'message' => "Choose which {$functionEntry['name']} cell on the City Grid should be the end point.",
                'placements' => $placements,
            ];
        }

        $isValidPlacement = collect($placements)->contains(
            fn (array $placement) => (int) $placement['row'] === $row && (int) $placement['col'] === $col
        );

        if (!$isValidPlacement) {
            return [
                'success' => false,
                'error' => 'invalid_endpoint_cell',
                'message' => "This cell does not contain {$functionEntry['name']}.",
            ];
        }

        $cell = GridCell::where('row', $row)->where('col', $col)->first();

        if (!$cell || (int) $cell->function_id !== $functionId) {
            return [
                'success' => false,
                'error' => 'invalid_endpoint_cell',
                'message' => "This cell does not contain {$functionEntry['name']}.",
            ];
        }

        $route->update([
            'end_row' => $row,
            'end_col' => $col,
            'end_function_id' => $functionId,
            'path_cells' => null,
        ]);

        return ['success' => true, 'route' => $route->fresh()->load('endFunction')];
    }

    /**
     * @return array{success: bool, route?: EventRoute, error?: string, message?: string}
     */
    public function generateRoute(SimulationEvent $event): array
    {
        $route = EventRoute::where('simulation_event_id', $event->id)->first();

        if (!$route || $route->start_row === null || $route->start_col === null) {
            return [
                'success' => false,
                'error' => 'missing_start_point',
                'message' => 'Set a start point on a Road cell before generating a route.',
            ];
        }

        if ($route->end_row === null || $route->end_function_id === null) {
            return [
                'success' => false,
                'error' => 'missing_endpoint',
                'message' => 'Set an end point before generating a route.',
            ];
        }

        $result = $this->pathfindingService->findPath(
            (int) $route->start_row,
            (int) $route->start_col,
            (int) $route->end_row,
            (int) $route->end_col,
            (int) $route->end_function_id
        );

        if (!$result['success']) {
            return $result;
        }

        $route->update(['path_cells' => $result['path']]);

        return ['success' => true, 'route' => $route->fresh()->load('endFunction')];
    }

    /**
     * @return array{can_create: bool, error?: string, message?: string}
     */
    public function assessRouteCreation(?EventRoute $route): array
    {
        if (
            !$route
            || $route->start_row === null
            || $route->start_col === null
            || $route->end_row === null
            || $route->end_function_id === null
        ) {
            return ['can_create' => false];
        }

        $result = $this->pathfindingService->findPath(
            (int) $route->start_row,
            (int) $route->start_col,
            (int) $route->end_row,
            (int) $route->end_col,
            (int) $route->end_function_id
        );

        if ($result['success']) {
            return ['can_create' => true];
        }

        return [
            'can_create' => false,
            'error' => $result['error'] ?? 'route_blocked',
            'message' => $result['message'] ?? 'The route cannot be created for this event.',
        ];
    }

    /**
     * @param list<array{row: int, col: int}> $pathCells
     * @return array{success: bool, route?: EventRoute, error?: string, message?: string}
     */
    public function storeManualPath(SimulationEvent $event, array $pathCells): array
    {
        $route = EventRoute::where('simulation_event_id', $event->id)->first();

        if (!$route || $route->start_row === null || $route->start_col === null) {
            return [
                'success' => false,
                'error' => 'missing_start_point',
                'message' => 'Set a start point on a Road cell before drawing a route.',
            ];
        }

        if ($route->end_row === null || $route->end_function_id === null) {
            return [
                'success' => false,
                'error' => 'missing_endpoint',
                'message' => 'Set an end point before drawing a route.',
            ];
        }

        $result = $this->pathfindingService->validateManualPath(
            (int) $route->start_row,
            (int) $route->start_col,
            (int) $route->end_row,
            (int) $route->end_col,
            (int) $route->end_function_id,
            $pathCells
        );

        if (!$result['success']) {
            return $result;
        }

        $route->update(['path_cells' => $result['path']]);

        return ['success' => true, 'route' => $route->fresh()->load('endFunction')];
    }

    /**
     * @return array{success: bool, route?: EventRoute, error?: string, message?: string}
     */
    public function clearPath(SimulationEvent $event): array
    {
        $route = EventRoute::where('simulation_event_id', $event->id)->first();

        if (!$route || $route->path_cells === null) {
            return [
                'success' => false,
                'error' => 'missing_path',
                'message' => 'This event has no route to remove.',
            ];
        }

        $route->update(['path_cells' => null]);

        return ['success' => true, 'route' => $route->fresh()->load('endFunction')];
    }

    /**
     * @return array{success: bool, route?: EventRoute, error?: string, message?: string}
     */
    public function clearEndpoint(SimulationEvent $event): array
    {
        $route = EventRoute::where('simulation_event_id', $event->id)->first();

        if (!$route || $route->start_row === null || $route->start_col === null) {
            return [
                'success' => false,
                'error' => 'missing_start_point',
                'message' => 'Set a start point before managing an end point.',
            ];
        }

        if ($route->end_row === null && $route->end_function_id === null) {
            return [
                'success' => false,
                'error' => 'missing_endpoint',
                'message' => 'This event has no end point to delete.',
            ];
        }

        $route->update($this->clearEndpointAttributes());

        return ['success' => true, 'route' => $route->fresh()->load('endFunction')];
    }

    /**
     * Keep stored route points aligned after a grid function move.
     *
     * @return list<EventRoute>
     */
    public function syncAfterGridMove(?int $oldRow, ?int $oldCol, int $newRow, int $newCol, int $functionId): array
    {
        $roadFunctionIds = $this->roadFunctionIds();
        $isRoad = in_array($functionId, $roadFunctionIds, true);
        $movedFromGrid = $oldRow !== null && $oldCol !== null;

        foreach (EventRoute::all() as $route) {
            $updates = [];

            if ($movedFromGrid) {
                if ($this->matchesCell($route->start_row, $route->start_col, $oldRow, $oldCol)) {
                    if ($isRoad) {
                        $updates['start_row'] = $newRow;
                        $updates['start_col'] = $newCol;
                    } else {
                        $updates = array_merge($updates, $this->clearStartAttributes());
                    }
                }

                if ($route->end_row !== null && $this->matchesCell($route->end_row, $route->end_col, $oldRow, $oldCol)) {
                    if ((int) $route->end_function_id === $functionId) {
                        $updates['end_row'] = $newRow;
                        $updates['end_col'] = $newCol;
                    } elseif (!$this->endpointFunctionIsOnGridCell($route, $oldRow, $oldCol)) {
                        $updates = array_merge($updates, $this->clearEndpointCoordinates());
                    }
                }
            }

            if ($route->start_row !== null && $this->matchesCell($route->start_row, $route->start_col, $newRow, $newCol) && !$isRoad) {
                $updates = array_merge($updates, $this->clearStartAttributes());
            }

            if ($route->end_row !== null && $this->matchesCell($route->end_row, $route->end_col, $newRow, $newCol)) {
                if ((int) $route->end_function_id !== $functionId) {
                    $updates = array_merge($updates, $this->clearEndpointCoordinates());
                }
            }

            if ($isRoad && $route->start_row === null && $this->routeHasPlannedEndpoint($route)) {
                $updates['start_row'] = $newRow;
                $updates['start_col'] = $newCol;
            }

            if (
                (int) $route->end_function_id === $functionId
                && $route->end_row === null
                && $route->start_row !== null
            ) {
                $updates['end_row'] = $newRow;
                $updates['end_col'] = $newCol;
            }

            if ($updates !== []) {
                $updates['path_cells'] = null;
                $route->update($updates);
            }
        }

        foreach (EventRoute::all() as $route) {
            $this->validateRouteCells($route);
        }

        return EventRoute::query()
            ->with(['simulationEvent:id,name', 'endFunction:id,name'])
            ->get()
            ->all();
    }

    /**
     * Keep stored route points aligned after a grid function is removed.
     *
     * @return list<EventRoute>
     */
    public function syncAfterGridFunctionRemoved(int $row, int $col, ?int $functionId = null): array
    {
        foreach (EventRoute::all() as $route) {
            $updates = [];

            if ($this->matchesCell($route->start_row, $route->start_col, $row, $col)) {
                $updates = array_merge($updates, $this->clearStartAttributes());
            }

            if ($route->end_row !== null && $this->matchesCell($route->end_row, $route->end_col, $row, $col)) {
                $updates = array_merge($updates, $this->clearEndpointCoordinates());
            }

            if ($updates !== []) {
                $updates['path_cells'] = null;
                $route->update($updates);
            }
        }

        foreach (EventRoute::all() as $route) {
            $this->validateRouteCells($route);
        }

        return EventRoute::query()
            ->with(['simulationEvent:id,name', 'endFunction:id,name'])
            ->get()
            ->all();
    }

    public function roadFunctionId(): ?int
    {
        return $this->roadFunctionIds()[0] ?? null;
    }

    /**
     * @return list<int>
     */
    public function roadFunctionIds(): array
    {
        $road = strtolower(self::MAIN_ACCESS_ROAD_NAME);

        return CityFunction::query()
            ->whereRaw('LOWER(TRIM(name)) LIKE ?', ['%' . $road . '%'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, placements: list<array{row: int, col: int}>}>
     */
    private function assignedFunctionsWithPlacements(SimulationEvent $event): array
    {
        $event->loadMissing('effects.cityFunction');

        $functionIds = $event->effects
            ->pluck('city_function_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($functionIds->isEmpty()) {
            return [];
        }

        $cells = GridCell::query()
            ->whereIn('function_id', $functionIds->all())
            ->get()
            ->groupBy('function_id');

        $functions = CityFunction::query()
            ->whereIn('id', $functionIds->all())
            ->get()
            ->keyBy('id');

        $result = [];

        foreach ($functionIds as $functionId) {
            $function = $functions->get($functionId);
            if (!$function) {
                continue;
            }

            $placements = ($cells->get($functionId) ?? collect())
                ->map(fn (GridCell $cell) => [
                    'row' => (int) $cell->row,
                    'col' => (int) $cell->col,
                ])
                ->values()
                ->all();

            $result[] = [
                'id' => (int) $functionId,
                'name' => $function->name,
                'placements' => $placements,
            ];
        }

        return $result;
    }

    private function matchesCell(?int $row, ?int $col, ?int $targetRow, ?int $targetCol): bool
    {
        return $row !== null
            && $col !== null
            && $targetRow !== null
            && $targetCol !== null
            && (int) $row === (int) $targetRow
            && (int) $col === (int) $targetCol;
    }

    /**
     * @return array<string, null>
     */
    private function clearRouteAttributes(): array
    {
        return [
            'start_row' => null,
            'start_col' => null,
            'end_row' => null,
            'end_col' => null,
            'end_function_id' => null,
            'path_cells' => null,
        ];
    }

    /**
     * @return array<string, null>
     */
    private function clearEndpointAttributes(): array
    {
        return [
            'end_row' => null,
            'end_col' => null,
            'end_function_id' => null,
            'path_cells' => null,
        ];
    }

    /**
     * @return array<string, null>
     */
    private function clearEndpointCoordinates(): array
    {
        return [
            'end_row' => null,
            'end_col' => null,
        ];
    }

    /**
     * @return array<string, null>
     */
    private function clearStartAttributes(): array
    {
        return [
            'start_row' => null,
            'start_col' => null,
            'path_cells' => null,
        ];
    }

    private function routeHasPlannedEndpoint(EventRoute $route): bool
    {
        return $route->end_row !== null || $route->end_function_id !== null;
    }

    private function endpointFunctionIsOnGridCell(EventRoute $route, int $row, int $col): bool
    {
        if ($route->end_function_id === null) {
            return false;
        }

        $cell = GridCell::query()
            ->where('row', $row)
            ->where('col', $col)
            ->first();

        return $cell !== null
            && (int) $cell->function_id === (int) $route->end_function_id;
    }

    private function validateRouteCells(EventRoute $route): void
    {
        if ($route->start_row !== null) {
            $startCell = $this->findMainAccessRoadCell((int) $route->start_row, (int) $route->start_col);

            if (!$startCell) {
                $route->update($this->clearStartAttributes());

                return;
            }
        }

        if ($route->end_row === null) {
            return;
        }

        $endCell = GridCell::query()
            ->where('row', $route->end_row)
            ->where('col', $route->end_col)
            ->first();

        if (!$endCell || (int) $endCell->function_id !== (int) $route->end_function_id) {
            $route->update(array_merge($this->clearEndpointCoordinates(), ['path_cells' => null]));
            return;
        }

        if ($route->path_cells !== null && !$this->storedPathIsStillValid($route)) {
            $route->update(['path_cells' => null]);
        }
    }

    private function storedPathIsStillValid(EventRoute $route): bool
    {
        if (!is_array($route->path_cells) || $route->path_cells === []) {
            return false;
        }

        $result = $this->pathfindingService->validateManualPath(
            (int) $route->start_row,
            (int) $route->start_col,
            (int) $route->end_row,
            (int) $route->end_col,
            (int) $route->end_function_id,
            $route->path_cells
        );

        return $result['success'];
    }
}
