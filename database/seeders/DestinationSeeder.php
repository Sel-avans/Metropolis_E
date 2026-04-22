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
            ['name' => 'Park', 'color' => '#2ecc71'],      // Green
            ['name' => 'Woonwijk', 'color' => '#3498db'],  // Blue
            ['name' => 'Centrum', 'color' => '#e74c3c'],   // Red
            ['name' => 'Industrie', 'color' => '#f1c40f'], // Yellow
            ['name' => 'Water', 'color' => '#1abc9c'],     // Turquoise
        ];

        foreach ($destinations as $data) {
            Destination::create($data);
        }
    }
}