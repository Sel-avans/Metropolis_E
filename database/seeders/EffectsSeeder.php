<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CityFunction;
use App\Models\Effect;

class EffectsSeeder extends Seeder
{
    public function run()
    {
        $data = [

            'Police Station' => [
                'safety'      => 5,
                'recreation'  => 1,
                'environment' => 1,
                'amenities'   => 0,
                'mobility'    => 2
            ],
            'Fire Station' => [
                'safety'      => 4,
                'recreation'  => 1,
                'environment' => 1,
                'amenities'   => 2,
                'mobility'    => 2
            ],

            'Park' => [
                'safety'      => -2,
                'recreation'  => 5,
                'environment' => 5,
                'amenities'   => 4,
                'mobility'    => 0
            ],
            'Cinema' => [
                'safety'      => -1,
                'recreation'  => 4,
                'environment' => 4,
                'amenities'   => 0,
                'mobility'    => 0
            ],
            'Sports Park' => [
                'safety'      => 0,
                'recreation'  => 5,
                'environment' => 5,
                'amenities'   => 2,
                'mobility'    => 0
            ],

            'School' => [
                'safety'      => 2,
                'recreation'  => 2,
                'environment' => 0,
                'amenities'   => 5,
                'mobility'    => -3
            ],
            'Store' => [
                'safety'      => 0,
                'recreation'  => 0,
                'environment' => -2,
                'amenities'   => 5,
                'mobility'    => 0
            ],
            'Hospital' => [
                'safety'      => 3,
                'recreation'  => 0,
                'environment' => 0,
                'amenities'   => 5,
                'mobility'    => 0
            ],

            'Train Station' => [
                'safety'      => -2,
                'recreation'  => 2,
                'environment' => 0,
                'amenities'   => 4,
                'mobility'    => 5
            ],
            'Road' => [
                'safety'      => -4,
                'recreation'  => 2,
                'environment' => -4,
                'amenities'   => 3,
                'mobility'    => 5
            ],
            'Bicycle Path' => [
                'safety'      => 0,
                'recreation'  => 3,
                'environment' => 3,
                'amenities'   => 3,
                'mobility'    => 4
            ],
            'Water Treatment' => [
                'safety'      => 0,
                'recreation'  => 0,
                'environment' => 5,
                'amenities'   => 2,
                'mobility'    => 0
            ],

            'Gas Station' => [
                'safety'      => -2,
                'recreation'  => 0,
                'environment' => -4,
                'amenities'   => 1,
                'mobility'    => 4
            ],
        ];

        foreach ($data as $functionName => $effects) {
            $function = CityFunction::where('name', $functionName)->first();

            if (!$function) continue;

            foreach ($effects as $category => $value) {
                Effect::create([
                    'function_id' => $function->id,
                    'category'    => $category,
                    'value'       => $value
                ]);
            }
        }
    }
}
