<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulationEvent extends Model
{
   
    protected $table = 'simulation_events';

   
    protected $fillable = [
        'name',
        'description',
        'type',
        'start_moment',
        'end_moment',
        'recurring_schedule',
        'remaining_base_duration', 
        'last_updated_at',         
    ];
}