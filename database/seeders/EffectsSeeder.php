<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CityFunction;
use App\Models\Effect;   
use Illuminate\Support\Facades\DB;

class EffectsSeeder extends Seeder
{
public function run()
{
    $data = [

        'Politiebureau' => [
            'veiligheid' => 5,
            'recreatie' => 1,
            'milieukwaliteit' => 1,
            'voorzieningen' => 0,
            'mobiliteit' => 2
        ],
        'Brandweerkazerne' => [
            'veiligheid' => 4,
            'recreatie' => 1,
            'milieukwaliteit' => 1,
            'voorzieningen' => 2,
            'mobiliteit' => 2
        ],

        'Park' => [
            'veiligheid' => -2,
            'recreatie' => 5,
            'milieukwaliteit' => 5,
            'voorzieningen' => 4,
            'mobiliteit' => 0
        ],
        'Bioscoop' => [
            'veiligheid' => -1,
            'recreatie' => 4,
            'milieukwaliteit' => 4,
            'voorzieningen' => 0,
            'mobiliteit' => 0
        ],
        'Sportpark' => [
            'veiligheid' => 0,
            'recreatie' => 5,
            'milieukwaliteit' => 5,
            'voorzieningen' => 2,
            'mobiliteit' => 0
        ],

        'School' => [
            'veiligheid' => 2,
            'recreatie' => 2,
            'milieukwaliteit' => 0,
            'voorzieningen' => 5,
            'mobiliteit' => -3
        ],
        'Winkel' => [
            'veiligheid' => 0,
            'recreatie' => 0,
            'milieukwaliteit' => -2,
            'voorzieningen' => 5,
            'mobiliteit' => 0
        ],
        'Ziekenhuis' => [
            'veiligheid' => 3,
            'recreatie' => 0,
            'milieukwaliteit' => 0,
            'voorzieningen' => 5,
            'mobiliteit' => 0
        ],

        'Station' => [
            'veiligheid' => -2,
            'recreatie' => 2,
            'milieukwaliteit' => 0,
            'voorzieningen' => 4,
            'mobiliteit' => 5
        ],
        'Weg' => [
            'veiligheid' => -4,
            'recreatie' => 2,
            'milieukwaliteit' => -4,
            'voorzieningen' => 3,
            'mobiliteit' => 5
        ],
        'Fietspad' => [
            'veiligheid' => 0,
            'recreatie' => 3,
            'milieukwaliteit' => 3,
            'voorzieningen' => 3,
            'mobiliteit' => 4
        ],
        'Waterzuivering' => [
            'veiligheid' => 0,
            'recreatie' => 0,
            'milieukwaliteit' => 5,
            'voorzieningen' => 2,
            'mobiliteit' => 0
        ],

        'Tankstation' => [
            'veiligheid' => -2,
            'recreatie' => 0,
            'milieukwaliteit' => -4,
            'voorzieningen' => 1,
            'mobiliteit' => 4
        ],
    ];

    foreach ($data as $functionName => $effects) {
        $function = CityFunction::where('name', $functionName)->first();

        if (!$function) continue;

        foreach ($effects as $category => $value) {
            Effect::create([
                'city_function_id' => $function->id,
                'category' => $category,
                'value' => $value
            ]);
        }
        }
    }
}
