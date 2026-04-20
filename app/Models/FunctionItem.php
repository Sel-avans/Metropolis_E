<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FunctionItem extends Model
{
    protected $table = 'functions';

    protected $fillable = [
        'name',
        'category',
        'image'
    ];
}
