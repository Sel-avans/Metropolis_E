<?php

namespace App\Http\Controllers;

use App\Models\SimulationEvent;
use Illuminate\Http\Request;

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
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:one-off,recurring',
        ]);

        if ($request->type === 'one-off') {
            $request->validate([
                'start_moment' => 'required|date',
                'end_moment' => 'required|date|after_or_equal:start_moment',
            ]);
        } else {
            $request->validate([
                'recurring_schedule' => 'required|string',
            ]);
        }

        SimulationEvent::create($request->all());
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
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:one-off,recurring',
        ]);

        if ($request->type === 'one-off') {
            $request->validate([
                'start_moment' => 'required|date',
                'end_moment' => 'required|date|after_or_equal:start_moment',
            ]);
        } else {
            $request->validate([
                'recurring_schedule' => 'required|string',
            ]);
        }

        $event->update($request->all());
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
}