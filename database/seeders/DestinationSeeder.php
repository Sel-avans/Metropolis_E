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
        $destinations = [
            ['name' => 'Park',        'color' => '#2ecc71'],
            ['name' => 'Residential', 'color' => '#3498db'],
            ['name' => 'Downtown',    'color' => '#e74c3c'],
            ['name' => 'Industrial',  'color' => '#f1c40f'],
            ['name' => 'Water',       'color' => '#1abc9c'],
        ];

        foreach ($destinations as $data) {
            Destination::create($data);
        }
    }
}
