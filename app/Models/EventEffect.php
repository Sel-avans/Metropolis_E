<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventEffect extends Model
{
    use HasFactory;

    // Allow mass assignment for these columns
    protected $fillable = [
        'simulation_event_id',
        'city_function_id',
        'modifier'
    ];

    // Define the relationship to SimulationEvent
    public function simulationEvent()
    {
        return $this->belongsTo(SimulationEvent::class);
    }

    // Define the relationship to CityFunction
    public function cityFunction()
    {
        return $this->belongsTo(CityFunction::class);
    }
}