<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Condition extends Model
{
    protected $fillable = [
        'function_a',
        'function_b',
        'type',
        'value',
    ];

    public function functionA()
    {
        return $this->belongsTo(CityFunction::class, 'function_a');
    }

    public function functionB()
    {
        return $this->belongsTo(CityFunction::class, 'function_b');
    }

    public static function validateRule($data)
    {
        if ($data['function_a'] == $data['function_b']) {
            throw new \Exception("Function A and Function B cannot be the same.");
        }

        if (in_array($data['type'], ['bonus', 'penalty'])) {
            if (!isset($data['value']) || $data['value'] < -5 || $data['value'] > 5) {
                throw new \Exception("Value must be between -5 and +5 for bonus/penalty.");
            }
        }

        if ($data['type'] === 'forbidden') {
            if (!empty($data['value'])) {
                throw new \Exception("Value is only allowed for bonus/penalty types.");
            }
        }
    }
}
