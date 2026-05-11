<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdjacencyRule extends Model
{
    protected $fillable = [
        'function_a',
        'function_b',
        'type',
        'value'
    ];

    public function a()
    {
        return $this->belongsTo(CityFunction::class, 'function_a');
    }

    public function b()
    {
        return $this->belongsTo(CityFunction::class, 'function_b');
    }
}
