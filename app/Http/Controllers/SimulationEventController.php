<?php

namespace App\Http\Controllers;

use App\Models\CityFunction;
use App\Models\Effect;
use App\Models\EventEffect;
use App\Models\SimulationEvent;
use App\Services\EventModifierService;
use Illuminate\Http\Request;
use App\Services\SimSpeedService;

class SimulationEventController extends Controller
{
    public function index()
    {
        $events = SimulationEvent::with(['categoryEffects', 'effects.cityFunction'])
            ->orderByDesc('id')
            ->get();

        return view('events.index', compact('events'));
    }

    public function show(SimulationEvent $event)
    {
        $event->load('effects.cityFunction', 'categoryEffects');

        return view('events.show', compact('event'));
    }

    public function create()
    {
        return view('events.create', $this->eventFormData());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                   => 'required|string|max:255',
            'description'            => 'nullable|string',
            'type'                   => 'required|in:one-off,recurring',
            'start_moment'           => 'required_if:type,one-off|nullable|date',
            'end_moment'             => 'required_if:type,one-off|nullable|date|after_or_equal:start_moment',
            'recurring_schedule'     => 'required_if:type,recurring|nullable|string',
            'recurring_start_date'   => 'required_if:type,recurring|nullable|date',
            'recurring_end_date'     => 'nullable|date|after_or_equal:recurring_start_date',
            'recurring_start_time'   => 'required_if:type,recurring|nullable',
            'recurring_end_time'     => 'required_if:type,recurring|nullable',
            'category_modifiers'     => 'required|array|min:1',
            'category_modifiers.*'   => 'required|integer|min:-5|max:5|not_in:0',
            'city_functions'         => 'required|array|min:1',
            'city_functions.*'       => 'integer|exists:city_functions,id',
        ]);

        $event = SimulationEvent::create(
            EventModifierService::normalizeEventMoments($validated)
        );

        $this->syncEventEffects(
            $event,
            $validated['category_modifiers'],
            $validated['city_functions']
        );

        return redirect()->route('events.index')->with('success', 'Event created successfully.');
    }

    public function edit(SimulationEvent $event)
    {
        $event->load('effects', 'categoryEffects');

        return view('events.edit', array_merge($this->eventFormData(), [
            'event'                      => $event,
            'selectedCategoryModifiers'  => $event->categoryEffects
                ->mapWithKeys(fn (Effect $effect) => [strtolower($effect->category) => (int) $effect->value])
                ->all(),
            'selectedCityFunctionIds'    => $event->effects->pluck('city_function_id')->all(),
        ]));
    }

    public function update(Request $request, SimulationEvent $event)
    {
        $validated = $request->validate([
            'name'                   => 'required|string|max:255',
            'description'            => 'nullable|string',
            'type'                   => 'required|in:one-off,recurring',
            'start_moment'           => 'required_if:type,one-off|nullable|date',
            'end_moment'             => 'required_if:type,one-off|nullable|date|after_or_equal:start_moment',
            'recurring_schedule'     => 'required_if:type,recurring|nullable|string',
            'recurring_start_date'   => 'required_if:type,recurring|nullable|date',
            'recurring_end_date'     => 'required_if:type,recurring|nullable|date|after_or_equal:recurring_start_date',
            'recurring_start_time'   => 'required_if:type,recurring|nullable',
            'recurring_end_time'     => 'required_if:type,recurring|nullable',
            'category_modifiers'     => 'required|array|min:1',
            'category_modifiers.*'   => 'required|integer|min:-5|max:5|not_in:0',
            'city_functions'         => 'required|array|min:1',
            'city_functions.*'       => 'integer|exists:city_functions,id',
        ]);

        $event->update(EventModifierService::normalizeEventMoments($validated));

        $this->syncEventEffects(
            $event,
            $validated['category_modifiers'],
            $validated['city_functions']
        );

        return redirect()->route('events.index')->with('success', 'Event updated successfully.');
    }

    public function destroy(SimulationEvent $event)
    {
        $event->delete();

        return redirect()->route('events.index')->with('success', 'Event deleted successfully.');
    }

    public function active()
    {
        $now = EventModifierService::now();

        $events = EventModifierService::getActiveEvents()
            ->map(function (SimulationEvent $event) use ($now) {
                $timing = null;

                if ($event->type === 'one-off' && $event->end_moment) {
                    $end  = EventModifierService::parseMoment($event->end_moment);
                    $mins = max(0, (int) round($now->diffInMinutes($end, false)));
                    $timing = 'Ends in ' . $mins . ' min';
                }

                if ($event->type === 'recurring') {
                    $timing = 'Pattern: ' . ($event->recurring_schedule ?? 'unknown');
                }

                $modifiers = $event->categoryEffects->mapWithKeys(function (Effect $effect) {
                    return [strtolower($effect->category) => (float) $effect->value];
                });

                return [
                    'id'              => $event->id,
                    'name'            => $event->name,
                    'type'            => $event->type,
                    'timing'          => $timing,
                    'end_at'          => $event->end_moment
                        ? EventModifierService::parseMoment($event->end_moment)->timestamp
                        : null,
                    'ends_at_display' => $event->end_moment
                        ? EventModifierService::formatForDisplay($event->end_moment)
                        : null,
                    'modifiers'       => $modifiers->isEmpty() ? (object) [] : $modifiers->toArray(),
                ];
            })
            ->values();

        return response()->json([
            'status'    => 'success',
            'timestamp' => $now->toIso8601String(),
            'events'    => $events,
        ]);
    }

    /**
     * API voor de simulatie: geeft ALLE events terug (niet gefilterd op actief),
     * inclusief start/end als simulatieminuten (0 = 06:00, 1440 = volgende dag 06:00).
     */
    public function simulation()
    {
        $events = SimulationEvent::with('categoryEffects', 'effects.cityFunction')
            ->orderBy('id')
            ->get()
            ->map(function (SimulationEvent $event) {

                $modifiers = $event->categoryEffects->mapWithKeys(function (Effect $effect) {
                    return [strtolower($effect->category) => (float) $effect->value];
                });

                // Bepaal start- en eindtijd als minuten van de dag (0–1440)
                // Start_minutes en end_minutes zijn relatief aan 06:00 (offset = 360 min)
                // zodat de JS-kant direct kan vergelijken met de simulatietijd.
                [$startMinutes, $endMinutes] = array_slice(
                    EventModifierService::resolveSimulationWindow($event),
                    0,
                    2
                );
                $durationMinutes = EventModifierService::eventDurationMinutes($event);
                $calendarDuration = EventModifierService::calendarDurationMinutes($event);
                $fitsInCycle = EventModifierService::fitsInSimulationCycle($event);

                // Welke categorieën beïnvloedt dit event?
                // Wordt gebruikt door de highlight-logica in grid.js
                $affectedCategories = $event->categoryEffects
                    ->pluck('category')
                    ->map(fn ($c) => strtolower($c))
                    ->unique()
                    ->values()
                    ->all();

                $affectedFunctionIds = $event->effects
                    ->pluck('city_function_id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();

                return [
                    'id'                  => $event->id,
                    'name'                => $event->name,
                    'type'                => $event->type,
                    'recurring_schedule'  => $event->recurring_schedule,
                    'recurring_start_date'=> $event->recurring_start_date,
                    'recurring_end_date'  => $event->recurring_end_date,
                    'start_minutes'       => $startMinutes,
                    'end_minutes'         => $endMinutes,
                    'duration_minutes'    => $durationMinutes,
                    'calendar_duration_minutes' => $calendarDuration,
                    'fits_in_cycle'       => $fitsInCycle,
                    'modifiers'           => $modifiers->isEmpty() ? (object) [] : $modifiers->toArray(),
                    'affected_categories' => $affectedCategories,
                    'affected_function_ids' => $affectedFunctionIds,
                ];
            })
            ->values();

        return response()->json([
            'status' => 'success',
            'simulation_reference_date' => EventModifierService::now()
                ->timezone(EventModifierService::DISPLAY_TIMEZONE)
                ->toDateString(),
            'events' => $events,
        ]);
    }

    // =========================================================
    // PRIVATE HELPERS
    // =========================================================

    /** @return array<string, mixed> */
    private function eventFormData(): array
    {
        return [
            'effectCategories'          => $this->distinctEffectCategories(),
            'cityFunctions'             => CityFunction::orderBy('name')->get(),
            'selectedCategoryModifiers' => [],
            'selectedCityFunctionIds'   => [],
        ];
    }

    /** @return list<string> */
    private function distinctEffectCategories(): array
    {
        $fromEffects = Effect::query()
            ->whereNull('simulation_event_id')
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $fromFunctions = CityFunction::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return $fromEffects
            ->merge($fromFunctions)
            ->map(fn ($category) => strtolower((string) $category))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, int|string>  $categoryModifiers
     * @param  array<int|string>          $cityFunctionIds
     */
    private function syncEventEffects(
        SimulationEvent $event,
        array $categoryModifiers,
        array $cityFunctionIds
    ): void {
        $event->categoryEffects()->delete();
        $event->effects()->delete();

        foreach ($categoryModifiers as $category => $value) {
            if (! is_numeric($value) || (int) $value === 0) {
                continue;
            }
            Effect::create([
                'function_id'         => null,
                'simulation_event_id' => $event->id,
                'category'            => strtolower((string) $category),
                'value'               => (int) $value,
            ]);
        }

        foreach (array_unique(array_map('intval', $cityFunctionIds)) as $functionId) {
            EventEffect::create([
                'simulation_event_id' => $event->id,
                'city_function_id'    => $functionId,
                'modifier'            => 0,
            ]);
        }
    }

    public function changeSpeed(Request $request)
    {
        $request->validate(['speed' => 'required|numeric|min:0.1']);

        $newSpeed = (float) $request->input('speed');
        $oldSpeed = (float) session('current_simulation_speed', 1.0);

        $manager = new SimSpeedService();
        $events  = SimulationEvent::all();

        foreach ($events as $event) {
            try {
                $updateData = $manager->updateSpeedChange($event, $oldSpeed, $newSpeed);
                $event->update($updateData);
            } catch (\Exception $e) {
                \Log::error("Fout bij update event ID {$event->id}: " . $e->getMessage());
            }
        }

        session(['current_simulation_speed' => $newSpeed]);

        return response()->json(['success' => true, 'new_speed' => $newSpeed]);
    }
}