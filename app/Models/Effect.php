<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Effect extends Model
{
    use HasFactory;

    protected $table = 'effects';

    protected $fillable = [
        'function_id',
        'category',
        'value',
        'simulation_event_id',
    ];

    public function function()
    {
        return $this->belongsTo(CityFunction::class, 'function_id');
    }

    public function simulationEvent()
{
    return $this->belongsTo(SimulationEvent::class, 'simulation_event_id');
}
}
