<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GridCell extends Model
{
    use HasFactory;
    protected $fillable = ['row', 'col', 'city_function_id'];

    public $timestamps = false;

    public function function()
    {
        return $this->belongsTo(CityFunction::class, 'city_function_id');
    }
}

