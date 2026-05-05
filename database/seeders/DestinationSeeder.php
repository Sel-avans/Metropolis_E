<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Destination;

class DestinationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define the default destinations for the library
        $destinations = [
            ['name' => 'Park', 'color' => '#2ecc71'],
            ['name' => 'Woonwijk', 'color' => '#3498db'],
            ['name' => 'Centrum', 'color' => '#e74c3c'],
            ['name' => 'Industrie', 'color' => '#f1c40f'],
            ['name' => 'Water', 'color' => '#1abc9c'],
        ];

        foreach ($destinations as $data) {
            Destination::create($data);
        }
    }
}