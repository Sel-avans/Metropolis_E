<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CityFunction;

class CityFunctionsSeeder extends Seeder
{
    public function run()
    {
        $functions = [
            ['name' => 'Police Station',     'category' => 'safety',        'image' => 'icons/police.png'],
            ['name' => 'Fire Station',       'category' => 'safety',        'image' => 'icons/firestation.png'],

            ['name' => 'Park',               'category' => 'recreation',    'image' => 'icons/park.png',        'sensitivity' => 'sensitive'],
            ['name' => 'Cinema',             'category' => 'recreation',    'image' => 'icons/bioscoop.png'],
            ['name' => 'Sports Park',        'category' => 'recreation',    'image' => 'icons/sportpark.png'],

            ['name' => 'Water Treatment',    'category' => 'environment',   'image' => 'icons/waterzuivering.png'],

            ['name' => 'School',             'category' => 'amenities',     'image' => 'icons/school.png',      'sensitivity' => 'sensitive'],
            ['name' => 'Store',              'category' => 'amenities',     'image' => 'icons/mall.png',        'pollution' => 'polluting'],
            ['name' => 'Hospital',           'category' => 'amenities',     'image' => 'icons/hospital.png',    'sensitivity' => 'sensitive'],

            ['name' => 'Train Station',      'category' => 'mobility',      'image' => 'icons/station.png'],
            ['name' => 'Road',               'category' => 'mobility',      'image' => 'icons/road.png',        'pollution' => 'polluting'],
            ['name' => 'Bicycle Path',       'category' => 'mobility',      'image' => 'icons/fietspad.png'],
            ['name' => 'Gas Station',        'category' => 'mobility',      'image' => 'icons/tankstation.png', 'pollution' => 'polluting'],
        ];

        foreach ($functions as $f) {
            CityFunction::create($f);
        }
    }
}
