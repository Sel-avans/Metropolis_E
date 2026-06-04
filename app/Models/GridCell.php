<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GridCell extends Model
{
    use HasFactory;

   
    protected $fillable = ['row', 'col', 'function_id', 'is_approved'];

    
    protected $casts = [
        'is_approved' => 'boolean',
    ];

    public $timestamps = false;

    public function function()
    {
        return $this->belongsTo(CityFunction::class, 'function_id');
    }
}