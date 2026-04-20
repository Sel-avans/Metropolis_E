<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CityFunction extends Model
{
    protected $table = 'city_functions'; 
    protected $fillable = [
        'name',
        'type',
        'category',
        'image',
    ];
}
