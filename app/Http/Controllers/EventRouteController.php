<?php

namespace App\Http\Controllers;

use App\Models\EventRoute;
use App\Models\SimulationEvent;
use App\Services\EventRouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventRouteController extends Controller
{
    public function __construct(private EventRouteService $eventRouteService)
    {
    }

    public function index(): JsonResponse
    {
        $routes = EventRoute::query()
            ->with('simulationEvent:id,name')
            ->get()
            ->map(fn (EventRoute $route) => $this->formatRoute($route));

        return response()->json([
            'success' => true,
            'routes' => $routes,
            'road_function_id' => $this->eventRouteService->roadFunctionId(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['required', 'integer', 'exists:simulation_events,id'],
            'row' => ['required', 'integer', 'min:1', 'max:4'],
            'col' => ['required', 'integer', 'min:1', 'max:3'],
        ]);

        $event = SimulationEvent::findOrFail($validated['event_id']);
        $result = $this->eventRouteService->setStartPoint(
            $event,
            (int) $validated['row'],
            (int) $validated['col']
        );

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        return response()->json([
            'success' => true,
            'route' => $this->formatRoute($result['route']),
        ]);
    }

    public function destroy(SimulationEvent $event): JsonResponse
    {
        $deleted = EventRoute::where('simulation_event_id', $event->id)->delete() > 0;

        return response()->json([
            'success' => true,
            'deleted' => $deleted,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRoute(EventRoute $route): array
    {
        return [
            'id' => $route->id,
            'event_id' => $route->simulation_event_id,
            'event_name' => $route->simulationEvent?->name,
            'start_row' => $route->start_row,
            'start_col' => $route->start_col,
        ];
    }
}
