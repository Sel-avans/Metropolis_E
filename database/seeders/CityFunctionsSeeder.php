<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CityFunction;

class CityFunctionsSeeder extends Seeder
{
    public function run(): void
    {
        // Define some default city functions
        $functions = [
            ['name' => 'Park', 'category' => 'Nature', 'image' => 'images/park.png'],
            ['name' => 'House', 'category' => 'Residential', 'image' => 'images/house.png'],
            ['name' => 'Office', 'category' => 'Commercial', 'image' => 'images/office.png'],
        ];

        foreach ($functions as $func) {
            CityFunction::create($func);
        }
    }
}