<?php

namespace App\Http\Controllers;

use App\Models\Event; 
use Carbon\Carbon;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index()
    {
        return view('events.index');
    }

    public function active()
{
    $now = Carbon::now();

    $activeEvents = Event::where('start_at', '<=', $now)
        ->where('end_at', '>=', $now)
        ->get();

    $formattedEvents = $activeEvents->map(function ($event) {
        return [
            'name'   => $event->name,
            // Dit geeft een puur getal (bijv. 1779934200), perfect voor Javascript
            'end_at' => $event->end_at ? $event->end_at->timestamp : null, 
            'timing' => 'Actief...',
        ];
    });

    return response()->json([
        'events' => $formattedEvents->values()->all()
    ]);
}
}