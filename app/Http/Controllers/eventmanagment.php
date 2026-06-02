<?php

namespace App\Http\Controllers;

use App\Models\SimulationEvent; 
use App\Services\DynamicEventManager; 
use Illuminate\Http\Request;

class SimulationEventController extends Controller
{

    
    public function changeSpeed(Request $request)
    {
        
        $newSpeed = (float) $request->input('speed', 1.0);
        $oldSpeed = (float) session('current_simulation_speed', 1.0);

       
        $events = SimulationEvent::all();
        $manager = new DynamicEventManager();

        
        foreach ($events as $event) {
            $updateData = $manager->updateSpeedChange($event, $oldSpeed, $newSpeed);
            $event->update($updateData);
        }

        session(['current_simulation_speed' => $newSpeed]);

        
        return response()->json([
            'success' => true,
            'message' => "Simulationspeed changed to {$newSpeed}x. Events caculated live here!",
            'new_speed' => $newSpeed
        ]);
    }
}