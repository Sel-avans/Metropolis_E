<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UndoAction extends Model
{
    protected $fillable = [
        'row',
        'col',
        'new_row', 
        'new_col',
        'previous_function_id',
        'action_type',
    ];
}
