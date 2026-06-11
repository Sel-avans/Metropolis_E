<?php

namespace App\Services;

use App\Models\CityFunction;
use App\Models\EventRoute;
use App\Models\GridCell;
use App\Models\SimulationEvent;

class EventRouteService
{
    public const MAIN_ACCESS_ROAD_NAME = 'Road';

    public function isMainAccessRoadCell(GridCell $cell): bool
    {
        if (!$cell->function_id) {
            return false;
        }

        $cell->loadMissing('function');

        return $cell->function
            && strcasecmp($cell->function->name, self::MAIN_ACCESS_ROAD_NAME) === 0;
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
                'message' => 'The start point must be a main access road (Road function) on the grid.',
            ];
        }

        $route = EventRoute::updateOrCreate(
            ['simulation_event_id' => $event->id],
            ['start_row' => $row, 'start_col' => $col]
        );

        return ['success' => true, 'route' => $route->fresh()];
    }

    public function roadFunctionId(): ?int
    {
        return CityFunction::query()
            ->whereRaw('LOWER(name) = ?', [strtolower(self::MAIN_ACCESS_ROAD_NAME)])
            ->value('id');
    }
}
