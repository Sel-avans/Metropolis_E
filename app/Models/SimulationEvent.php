<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SimulationEvent extends Model
{
    protected $fillable = [
        'name', 
        'description', 
        'type', 
        'start_moment', 
        'end_moment', 
        'recurring_schedule',
        'recurring_start_date',
        'recurring_end_date',
        'recurring_start_time',
        'recurring_end_time'
    ];

    public function effects()
        {
            return $this->hasMany(Effect::class, 'simulation_event_id');
        }
}
