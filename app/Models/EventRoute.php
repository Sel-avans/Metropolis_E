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
        'end_row',
        'end_col',
        'end_function_id',
        'path_cells',
    ];

    protected $casts = [
        'path_cells' => 'array',
    ];

    public function simulationEvent(): BelongsTo
    {
        return $this->belongsTo(SimulationEvent::class);
    }

    public function endFunction(): BelongsTo
    {
        return $this->belongsTo(CityFunction::class, 'end_function_id');
    }
}
