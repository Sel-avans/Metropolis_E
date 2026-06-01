<?php

namespace App\Http\Controllers;

use App\Models\SimulationEvent;
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
            'recurring_start_date' => 'required_if:type,recurring|nullable|date',
            'recurring_end_date' => 'nullable|date|after_or_equal:recurring_start_date',
            'recurring_start_time' => 'required_if:type,recurring|nullable',
            'recurring_end_time'   => 'required_if:type,recurring|nullable',
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
            'recurring_start_date' => 'required_if:type,recurring|nullable|date',
            'recurring_end_date'   => 'required_if:type,recurring|nullable|date|after_or_equal:recurring_start_date',
            'recurring_start_time' => 'required_if:type,recurring|nullable',
            'recurring_end_time'   => 'required_if:type,recurring|nullable',
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

   public function active()
{
    $now = Carbon::now('Europe/Amsterdam');

    $events = SimulationEvent::with('effects')
        ->get()
        ->map(function ($event) use ($now) {
            
            // Dwing Laravel om de meest recente effects uit de database te trekken (voorkomt cache-smetjes)
             $event->load('effects'); 

            $isActive = false;
            $timing = '';

            // Check voor one-off events
            if ($event->type === 'one-off' && $event->start_moment && $event->end_moment) {
                $start = Carbon::parse($event->start_moment, 'Europe/Amsterdam');
                $end = Carbon::parse($event->end_moment, 'Europe/Amsterdam');

                if ($now->between($start, $end)) {
                    $isActive = true;
                    $mins = round($now->diffInMinutes($end));
                    $timing = 'Ends in ' . $mins . ' min';
                }
            }

            // Check voor recurring events
            if ($event->type === 'recurring') {
                $isActive = true; 
                $timing = 'Pattern: ' . ($event->recurring_schedule ?? 'unknown');
            }

            if (!$isActive) {
                return null;
            }

            // Haal de categorieën en waarden op
            $modifiers = $event->effects->pluck('value', 'category');

            return [
                'id' => $event->id,
                'name' => $event->name,
                'type' => $event->type,
                'timing' => $timing,
                // Forceert altijd een JSON-object {}, ook als deze leeg is
                'modifiers' => $modifiers->isEmpty() ? (object)[] : $modifiers->toArray(),
            ];
        })
        ->filter()  
        ->values(); 

    return response()->json([
        'status' => 'success',
        'timestamp' => $now->toIso8601String(),
        'events' => $events
    ]);
}
}
