<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CityFunction;

class CityFunctionsSeeder extends Seeder
{
    public function run(): void
    {
        $functions = [
            ['name' => 'Woningen', 'category' => 'wonen', 'image' => 'images/house.png'],
            ['name' => 'Park', 'category' => 'natuur', 'image' => 'images/park.png'],
            ['name' => 'School', 'category' => 'onderwijs', 'image' => 'images/school.png'],
            ['name' => 'Supermarkt', 'category' => 'voorziening', 'image' => 'images/supermarkt.png'],
            ['name' => 'Ziekenhuis', 'category' => 'zorg', 'image' => 'images/ziekenhuis.png'],
            ['name' => 'Politiepost', 'category' => 'veiligheid', 'image' => 'images/politie.png'],
            ['name' => 'Brandweer', 'category' => 'veiligheid', 'image' => 'images/brandweer.png'],
            ['name' => 'Kantoor', 'category' => 'werk', 'image' => 'images/office.png'],
            ['name' => 'Sportcentrum', 'category' => 'recreatie', 'image' => 'images/sportcentrum.png'],
            ['name' => 'OV Hub', 'category' => 'infrastructuur', 'image' => 'images/ov.png'],
        ];

        foreach ($functions as $func) {
            // updateOrCreate prevents double data if you run the seeder twice
            CityFunction::updateOrCreate(['name' => $func['name']], $func);
        }
    }
}