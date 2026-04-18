<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GridState extends Model
{
    // De velden die ingevuld mogen worden
    protected $fillable = ['x', 'y', 'destination_id'];

    // Vertelt Laravel dat elke opgeslagen cel bij een bestemming uit de bibliotheek hoort
    public function destination()
    {
        return $this->belongsTo(Destination::class);
    }
}