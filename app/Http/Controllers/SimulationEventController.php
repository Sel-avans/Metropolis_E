<?php

namespace App\Http\Controllers;

use App\Models\SimulationEvent;
use App\Services\SimSpeedService; // Zorg dat deze service bestaat in App\Services
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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:one-off,recurring',
            'start_moment' => 'required_if:type,one-off|nullable|date',
            'end_moment' => 'required_if:type,one-off|nullable|date|after_or_equal:start_moment',
            'recurring_schedule' => 'required_if:type,recurring|nullable|string',
        ]);

        $event->update($validated);

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

    /**
     * API: Geeft actieve events terug voor de UI.
     */
    public function active()
    {
        $now = Carbon::now();

        $events = SimulationEvent::all()
            ->map(function ($event) use ($now) {
                $isActive = false;
                $timing = '';

                if ($event->type === 'one-off' && $event->start_moment && $event->end_moment) {
                    $start = Carbon::parse($event->start_moment);
                    $end = Carbon::parse($event->end_moment);

                    if ($now->between($start, $end)) {
                        $isActive = true;
                        $mins = $now->diffInMinutes($end);
                        $timing = 'Ends in ' . $mins . ' min';
                    }
                }

                if ($event->type === 'recurring') {
                    $isActive = true;
                    $timing = 'Next: ' . ($event->recurring_schedule ?? 'unknown');
                }

                if (! $isActive) return null;

                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'type' => $event->type,
                    'timing' => $timing,
                    'affected_functions' => 'Functions modified (from other subtask)',
                ];
            })
            ->filter()
            ->values();

        return response()->json(['events' => $events]);
    }

    /**
     * API: Update de simulatiesnelheid en herbereken actieve events.
     */
    public function changeSpeed(Request $request)
    {
        $request->validate([
            'speed' => 'required|numeric|min:0.1',
        ]);

        $newSpeed = (float) $request->input('speed');
        $oldSpeed = (float) session('current_simulation_speed', 1.0);

        $manager = new SimSpeedService();
        $events = SimulationEvent::all();

        // Update elk event via de service
        foreach ($events as $event) {
            try {
                $updateData = $manager->updateSpeedChange($event, $oldSpeed, $newSpeed);
                $event->update($updateData);
            } catch (\Exception $e) {
                \Log::error("Fout bij update event ID {$event->id}: " . $e->getMessage());
            }
        }

        session(['current_simulation_speed' => $newSpeed]);

        return response()->json([
            'success' => true,
            'new_speed' => $newSpeed
        ]);
    }
}