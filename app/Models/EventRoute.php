<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRoute extends Model
{
    protected $fillable = [
        'simulation_event_id',
        'start_row',
        'start_col',
    ];

    public function simulationEvent(): BelongsTo
    {
        return $this->belongsTo(SimulationEvent::class);
    }
}
