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
            ->with(['simulationEvent:id,name', 'endFunction:id,name'])
            ->get()
            ->map(fn (EventRoute $route) => $this->formatRoute($route));

        return response()->json([
            'success' => true,
            'routes' => $routes,
            'road_function_id' => $this->eventRouteService->roadFunctionId(),
            'road_function_ids' => $this->eventRouteService->roadFunctionIds(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['required', 'integer', 'exists:simulation_events,id'],
            'row' => ['required', 'integer', 'min:1', 'max:3'],
            'col' => ['required', 'integer', 'min:1', 'max:4'],
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

    public function endpointContext(SimulationEvent $event): JsonResponse
    {
        $result = $this->eventRouteService->getEndpointContext($event);

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        return response()->json([
            'success' => true,
            'assigned_functions' => $result['assigned_functions'],
            'route' => $this->formatRoute($result['route']),
        ]);
    }

    public function setEndpoint(Request $request, SimulationEvent $event): JsonResponse
    {
        $validated = $request->validate([
            'function_id' => ['required', 'integer', 'exists:city_functions,id'],
            'row' => ['nullable', 'integer', 'min:1', 'max:3'],
            'col' => ['nullable', 'integer', 'min:1', 'max:4'],
        ]);

        $result = $this->eventRouteService->setEndpoint(
            $event,
            (int) $validated['function_id'],
            isset($validated['row']) ? (int) $validated['row'] : null,
            isset($validated['col']) ? (int) $validated['col'] : null
        );

        if (!$result['success']) {
            $status = in_array($result['error'] ?? '', ['endpoint_choice_required', 'function_not_on_grid'], true)
                ? 422
                : 422;

            return response()->json($result, $status);
        }

        return response()->json([
            'success' => true,
            'route' => $this->formatRoute($result['route']),
        ]);
    }

    public function syncGridMove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'old_row' => ['nullable', 'integer', 'min:1', 'max:3'],
            'old_col' => ['nullable', 'integer', 'min:1', 'max:4'],
            'new_row' => ['required', 'integer', 'min:1', 'max:3'],
            'new_col' => ['required', 'integer', 'min:1', 'max:4'],
            'function_id' => ['required', 'integer', 'exists:city_functions,id'],
        ]);

        $routes = $this->eventRouteService->syncAfterGridMove(
            isset($validated['old_row']) ? (int) $validated['old_row'] : null,
            isset($validated['old_col']) ? (int) $validated['old_col'] : null,
            (int) $validated['new_row'],
            (int) $validated['new_col'],
            (int) $validated['function_id']
        );

        return response()->json([
            'success' => true,
            'routes' => collect($routes)->map(fn (EventRoute $route) => $this->formatRoute($route)),
        ]);
    }

    public function syncGridRemove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'row' => ['required', 'integer', 'min:1', 'max:3'],
            'col' => ['required', 'integer', 'min:1', 'max:4'],
            'function_id' => ['nullable', 'integer', 'exists:city_functions,id'],
        ]);

        $routes = $this->eventRouteService->syncAfterGridFunctionRemoved(
            (int) $validated['row'],
            (int) $validated['col'],
            isset($validated['function_id']) ? (int) $validated['function_id'] : null
        );

        return response()->json([
            'success' => true,
            'routes' => collect($routes)->map(fn (EventRoute $route) => $this->formatRoute($route)),
        ]);
    }

    public function destroyEndpoint(SimulationEvent $event): JsonResponse
    {
        $result = $this->eventRouteService->clearEndpoint($event);

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
        $route->loadMissing(['simulationEvent:id,name', 'endFunction:id,name']);

        return [
            'id' => $route->id,
            'event_id' => $route->simulation_event_id,
            'event_name' => $route->simulationEvent?->name,
            'start_row' => $route->start_row,
            'start_col' => $route->start_col,
            'end_row' => $route->end_row,
            'end_col' => $route->end_col,
            'end_function_id' => $route->end_function_id,
            'end_function_name' => $route->endFunction?->name,
        ];
    }
}
