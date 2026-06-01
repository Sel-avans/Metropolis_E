<?php

namespace App\Http\Controllers;

use App\Models\SimulationEvent;
use App\Services\EventModifierService;
use Illuminate\Http\Request;
use Carbon\Carbon; 

class SimulationEventController extends Controller
{
    /**
     * Display a listing of the events.
     */
    public function index()
    {
        $events = SimulationEvent::all();
        return view('events.index', compact('events'));
    }

    /**
     * Display the specified event.
     */
    public function show(SimulationEvent $event)
        {
            $event->load('effects');
            return view('events.show', compact('event'));
        }
    /**
     * Show the form for creating a new event.
     */
    public function create()
    {
        return view('events.create');
    }

    /**
     * Store a newly created event in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:one-off,recurring',
            'start_moment' => 'required_if:type,one-off|nullable|date',
            'end_moment' => 'required_if:type,one-off|nullable|date|after_or_equal:start_moment',
            'recurring_schedule' => 'required_if:type,recurring|nullable|string',
        ]);

        SimulationEvent::create(EventModifierService::normalizeEventMoments($validated));

        return redirect()->route('events.index')->with('success', 'Event created successfully.');
    }

    /**
     * Show the form for editing the specified event.
     */
    public function edit(SimulationEvent $event)
    {
        return view('events.edit', compact('event'));
    }

    /**
     * Update the specified event in storage.
     */
    public function update(Request $request, SimulationEvent $event)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:one-off,recurring',
            'start_moment' => 'required_if:type,one-off|nullable|date',
            'end_moment' => 'required_if:type,one-off|nullable|date|after_or_equal:start_moment',
            'recurring_schedule' => 'required_if:type,recurring|nullable|string',
        ]);

        $event->update(EventModifierService::normalizeEventMoments($validated));

        return redirect()->route('events.index')->with('success', 'Event updated successfully.');
    }

    /**
     * Remove the specified event from storage.
     */
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
                $timing = '';

                if ($event->type === 'one-off' && $event->end_moment) {
                    $end = EventModifierService::parseMoment($event->end_moment);
                    $mins = max(0, (int) round($now->diffInMinutes($end, false)));
                    $timing = 'Ends in ' . $mins . ' min';
                }

                if ($event->type === 'recurring') {
                    $timing = 'Pattern: ' . ($event->recurring_schedule ?? 'unknown');
                }

                $modifiers = $event->effects->mapWithKeys(function ($effect) {
                    return [strtolower($effect->category) => (float) $effect->value];
                });

                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'type' => $event->type,
                    'timing' => $timing,
                    'end_at' => $event->end_moment
                        ? EventModifierService::parseMoment($event->end_moment)->timestamp
                        : null,
                    'ends_at_display' => $event->end_moment
                        ? EventModifierService::formatForDisplay($event->end_moment)
                        : null,
                    'modifiers' => $modifiers->isEmpty() ? (object) [] : $modifiers->toArray(),
                ];
            })
            ->values();

        return response()->json([
            'status' => 'success',
            'timestamp' => $now->toIso8601String(),
            'events' => $events,
        ]);
    }
}
