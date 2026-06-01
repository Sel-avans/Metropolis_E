<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CityFunction extends Model
{
    use HasFactory;

    protected $table = 'city_functions';

    protected $fillable = [
        'name',
        'category',
        'image',
    ];

    public function effects()
    {
        return $this->hasMany(Effect::class, 'function_id')
            ->whereNull('simulation_event_id');
    }
}
