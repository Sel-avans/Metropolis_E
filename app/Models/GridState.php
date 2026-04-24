<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GridState extends Model
{
    protected $fillable = ['x', 'y', 'city_function_id'];

    public function cityFunction() 
    {
        return $this->belongsTo(CityFunction::class, 'city_function_id');
    }
}