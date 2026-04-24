<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Effect extends Model
{
    use HasFactory;
    protected $fillable = [
        'city_function_id',
        'category',
        'value',
    ];

    public function function()
    {
        return $this->belongsTo(CityFunction::class, 'city_function_id');
    }
}
