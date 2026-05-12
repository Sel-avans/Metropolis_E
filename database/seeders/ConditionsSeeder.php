<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Condition;

class ConditionsSeeder extends Seeder
{
    public function run()
    {
        $rules = [
            [1, 2, 'bonus', 2],
            [2, 1, 'bonus', 2],
            [3, 4, 'bonus', 2],
            [3, 5, 'bonus', 2],
            [4, 3, 'bonus', 2],
            [4, 5, 'bonus', 2],
            [5, 3, 'bonus', 2],
            [5, 4, 'bonus', 2],
            [7, 8, 'bonus', 2],
            [7, 9, 'bonus', 2],
            [8, 7, 'bonus', 2],
            [8, 9, 'bonus', 2],
            [9, 7, 'bonus', 2],
            [9, 8, 'bonus', 2],
            [10, 11, 'bonus', 2],
            [10, 12, 'bonus', 2],
            [10, 13, 'bonus', 2],
            [11, 10, 'bonus', 2],
            [11, 12, 'bonus', 2],
            [11, 13, 'bonus', 2],
            [12, 10, 'bonus', 2],
            [12, 11, 'bonus', 2],
            [12, 13, 'bonus', 2],
            [13, 10, 'bonus', 2],
            [13, 11, 'bonus', 2],
            [13, 12, 'bonus', 2],

            [3, 8, 'penalty', -2],
            [3, 11, 'penalty', -2],
            [3, 13, 'penalty', -2],
            [7, 8, 'penalty', -2],
            [7, 11, 'penalty', -2],
            [7, 13, 'penalty', -2],
            [9, 8, 'penalty', -2],
            [9, 11, 'penalty', -2],
            [9, 13, 'penalty', -2],
            
            [3, 13, 'forbidden', null], 
            [13, 3, 'forbidden', null],
        ];

        foreach ($rules as $r) {
            Condition::create([
                'function_a' => $r[0],
                'function_b' => $r[1],
                'type'       => $r[2],
                'value'      => $r[3],
            ]);
        }
    }
}
