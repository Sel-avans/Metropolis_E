<?php

namespace App\Http\Controllers;

use App\Models\SimulationEvent;
use App\Services\EventModifierService;
use Illuminate\Http\Request;
use Carbon\Carbon; 
use App\Services\SimSpeedService;

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
            'recurring_start_date' => 'required_if:type,recurring|nullable|date',
            'recurring_end_date' => 'nullable|date|after_or_equal:recurring_start_date',
            'recurring_start_time' => 'required_if:type,recurring|nullable',
            'recurring_end_time'   => 'required_if:type,recurring|nullable',
        ]);

        SimulationEvent::create(EventModifierService::attributesForPersistence($validated));

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
            'recurring_start_date' => 'required_if:type,recurring|nullable|date',
            'recurring_end_date'   => 'required_if:type,recurring|nullable|date|after_or_equal:recurring_start_date',
            'recurring_start_time' => 'required_if:type,recurring|nullable',
            'recurring_end_time'   => 'required_if:type,recurring|nullable',
        ]);

        $event->update(EventModifierService::attributesForPersistence($validated));

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

        $events = EventModifierService::getTrackedEvents($now)
            ->map(fn (SimulationEvent $event) => EventModifierService::formatEventForClient($event, $now))
            ->values();

        return response()->json([
            'status' => 'success',
            'timestamp' => $now->toIso8601String(),
            'server_now_ms' => $now->getTimestamp() * 1000,
            'events' => $events,
        ]);
    }
}
