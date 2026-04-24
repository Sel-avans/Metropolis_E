<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GridState extends Model
{
    // De velden die ingevuld mogen worden
    protected $fillable = ['x', 'y', 'city_function_id'];

    // Vertelt Laravel dat elke opgeslagen cel bij een bestemming uit de bibliotheek hoort
    public function cityFunction() 
    {
        return $this->belongsTo(CityFunction::class, 'city_function_id');
    }
}