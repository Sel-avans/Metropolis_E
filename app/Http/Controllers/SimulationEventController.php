<?php

namespace App\Http\Controllers;

use App\Models\SimulationEvent;
use Illuminate\Http\Request;
use Carbon\Carbon; // toegevoegd voor tijd berekeningen

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
        // Dynamically validate fields based on the chosen event type (one-off or recurring)
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:one-off,recurring',
            'start_moment' => 'required_if:type,one-off|nullable|date',
            'end_moment' => 'required_if:type,one-off|nullable|date|after_or_equal:start_moment',
            'recurring_schedule' => 'required_if:type,recurring|nullable|string',
        ]);

        // Create the event in the database
        SimulationEvent::create($validated);

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
        // Apply the same dynamic validation rules for updating
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:one-off,recurring',
            'start_moment' => 'required_if:type,one-off|nullable|date',
            'end_moment' => 'required_if:type,one-off|nullable|date|after_or_equal:start_moment',
            'recurring_schedule' => 'required_if:type,recurring|nullable|string',
        ]);

        // Update the database record
        $event->update($validated);

        return redirect()->route('events.index')->with('success', 'Event updated successfully.');
    }

    /**
     * Remove the specified event from storage.
     */
    public function destroy(SimulationEvent $event)
    {
        // Delete the event
        $event->delete();

        return redirect()->route('events.index')->with('success', 'Event deleted successfully.');
    }

    public function active()
{
    // Gebruik de juiste tijdzone (Nederlandse tijd)
    $now = Carbon::now('Europe/Amsterdam');

    $events = SimulationEvent::all()
        ->map(function ($event) use ($now) {

            $isActive = false;
            $timing = '';

            // Check voor one-off events
            if ($event->type === 'one-off' && $event->start_moment && $event->end_moment) {
                $start = Carbon::parse($event->start_moment, 'Europe/Amsterdam');
                $end = Carbon::parse($event->end_moment, 'Europe/Amsterdam');

                if ($now->between($start, $end)) {
                    $isActive = true;
                    $mins = $now->diffInMinutes($end);
                    $timing = 'Ends in ' . $mins . ' min';
                }
            }

            // Check voor recurring events
            if ($event->type === 'recurring') {
                $isActive = true;
                $timing = 'Next: ' . ($event->recurring_schedule ?? 'unknown');
            }

            // Als het event niet actief is, skippen we hem
            if (!$isActive) {
                return null;
            }

            // Dit sturen we terug voor actieve events
            return [
                'id' => $event->id,
                'name' => $event->name,
                'type' => $event->type,
                'timing' => $timing,
                'affected_functions' => 'Functions modified (from other subtask)',
            ];
        })
        ->filter()  // Verwijdert de 'null' waardes
        ->values(); // RESET de keys (0, 1, 2...) zodat het gegarandeerd een JSON array [] wordt

    return response()->json(['events' => $events]);
}
}
