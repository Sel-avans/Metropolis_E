<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'recurring_start_date',
        'recurring_end_date',
        'recurring_start_time',
        'recurring_end_time',
        'remaining_base_duration', 
        'last_updated_at', 
    ];

    /** Assigned city functions (destinations) for this event. */
    public function effects(): HasMany
    {
        return $this->hasMany(EventEffect::class, 'simulation_event_id');
    }

    /** Category-level QoL modifiers while this event is active. */
    public function categoryEffects(): HasMany
    {
        return $this->hasMany(Effect::class, 'simulation_event_id');
    }
}
