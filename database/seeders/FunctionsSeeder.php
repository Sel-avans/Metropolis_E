<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FunctionsSeeder extends Seeder
{
    /**
     * Run the database seed.
     */
    public function run(): void
    {
        DB::table('functions')->insert([
            [
                'name' => 'Woningen',
                'category' => 'wonen',
                'image' => 'woningen.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Park / Groenvoorziening',
                'category' => 'natuur',
                'image' => 'park.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Basisschool',
                'category' => 'onderwijs',
                'image' => 'basisschool.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Supermarkt',
                'category' => 'voorziening',
                'image' => 'supermarkt.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Ziekenhuis',
                'category' => 'zorg',
                'image' => 'ziekenhuis.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Politiepost',
                'category' => 'veiligheid',
                'image' => 'politie.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Brandweerkazerne',
                'category' => 'veiligheid',
                'image' => 'brandweer.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Kantoor / Bedrijven',
                'category' => 'werk',
                'image' => 'kantoor.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Sportcentrum',
                'category' => 'recreatie',
                'image' => 'sportcentrum.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Openbaar Vervoer Hub',
                'category' => 'infrastructuur',
                'image' => 'ov.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
