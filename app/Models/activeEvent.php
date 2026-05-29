<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class activeEvent extends Model 
{
    protected $table = 'active_events'; 

    protected $fillable = [
        'remaining_base_duration',
        'last_updated_at',
    ];
}