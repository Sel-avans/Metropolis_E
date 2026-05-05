<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CityFunction;

class CityFunctionsSeeder extends Seeder
{
    public function run()
    {
        $functions = [
            ['name' => 'Politiebureau', 'category' => 'veiligheid', 'image' => 'icons/police.png'],
            ['name' => 'Brandweerkazerne', 'category' => 'veiligheid', 'image' => 'icons/firestation.png'],

            ['name' => 'Park', 'category' => 'recreatie', 'image' => 'icons/park.png'],
            ['name' => 'Bioscoop', 'category' => 'recreatie', 'image' => 'icons/bioscoop.png'],
            ['name' => 'Sportpark', 'category' => 'recreatie', 'image' => 'icons/sportpark.png'],

            ['name' => 'Waterzuivering', 'category' => 'milieukwaliteit', 'image' => 'icons/waterzuivering.png'],


            ['name' => 'School', 'category' => 'voorzieningen', 'image' => 'icons/school.png'],
            ['name' => 'Winkel', 'category' => 'voorzieningen', 'image' => 'icons/mall.png'],
            ['name' => 'Ziekenhuis', 'category' => 'voorzieningen', 'image' => 'icons/hospital.png'],

            ['name' => 'Station', 'category' => 'mobiliteit', 'image' => 'icons/station.png'],
            ['name' => 'Weg', 'category' => 'mobiliteit', 'image' => 'icons/road.png'],
            ['name' => 'Fietspad', 'category' => 'mobiliteit', 'image' => 'icons/fietspad.png'],
            ['name' => 'Tankstation', 'category' => 'mobiliteit', 'image' => 'icons/tankstation.png'],
        ];

        foreach ($functions as $f) {
            CityFunction::create($f);
        }
    }
}
