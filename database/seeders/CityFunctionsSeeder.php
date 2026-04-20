<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CityFunction;

class CityFunctionsSeeder extends Seeder
{
    public function run(): void
    {
        $functions = [
            ['name' => 'School', 'category' => 'Education', 'image' => 'school.png'],
            ['name' => 'Daycare', 'category' => 'Education', 'image' => 'daycare.png'],
            ['name' => 'Hospital', 'category' => 'Health', 'image' => 'hospital.png'],
            ['name' => 'Doctor', 'category' => 'Health', 'image' => 'doctor.png'],
            ['name' => 'Police Station', 'category' => 'Safety', 'image' => 'police.png'],
            ['name' => 'Fire Station', 'category' => 'Safety', 'image' => 'firestation.png'],
            ['name' => 'Apartment', 'category' => 'Living', 'image' => 'apartment.png'],
            ['name' => 'Detached House', 'category' => 'Living', 'image' => 'detached.png'],
            ['name' => 'Row House', 'category' => 'Living', 'image' => 'rowhouse.png'],
            ['name' => 'Villa', 'category' => 'Living', 'image' => 'villa.png'],
            ['name' => 'Supermarket', 'category' => 'Commerce', 'image' => 'supermarket.png'],
            ['name' => 'Mall', 'category' => 'Commerce', 'image' => 'mall.png'],
            ['name' => 'Restaurant', 'category' => 'Commerce', 'image' => 'restaurant.png'],
            ['name' => 'Factory', 'category' => 'Industry', 'image' => 'factory.png'],
            ['name' => 'Warehouse', 'category' => 'Industry', 'image' => 'warehouse.png'],
            ['name' => 'Office', 'category' => 'Work', 'image' => 'office.png'],
            ['name' => 'Stadium', 'category' => 'Recreation', 'image' => 'stadium.png'],
            ['name' => 'Sports Center', 'category' => 'Recreation', 'image' => 'sports.png'],
            ['name' => 'Park', 'category' => 'Recreation', 'image' => 'park.png'],
            ['name' => 'Playground', 'category' => 'Recreation', 'image' => 'playground.png'],
            ['name' => 'Museum', 'category' => 'Culture', 'image' => 'museum.png'],
            ['name' => 'Theater', 'category' => 'Culture', 'image' => 'theater.png'],
            ['name' => 'Train Station', 'category' => 'Transport', 'image' => 'train.png'],
            ['name' => 'Bus Stop', 'category' => 'Transport', 'image' => 'bus.png'],
            ['name' => 'Bike Path', 'category' => 'Transport', 'image' => 'bikepath.png'],
            ['name' => 'Parking', 'category' => 'Transport', 'image' => 'parking.png'],
            ['name' => 'Road', 'category' => 'Transport', 'image' => 'road.png'],
            ['name' => 'Power Plant', 'category' => 'Infrastructure', 'image' => 'powerplant.png'],
            ['name' => 'Greenzone', 'category' => 'Nature', 'image' => 'greenzone.png'],
            ['name' => 'Forest', 'category' => 'Nature', 'image' => 'forest.png'],
            ['name' => 'River', 'category' => 'Nature', 'image' => 'river.png'],
            ['name' => 'Pond', 'category' => 'Nature', 'image' => 'pond.png'],
            ['name' => 'Pool', 'category' => 'Recreation', 'image' => 'pool.png'],
            ['name' => 'Senior Center', 'category' => 'Community', 'image' => 'senior.png'],
            ['name' => 'Student Housing', 'category' => 'Community', 'image' => 'student.png'],
            ['name' => 'City Area', 'category' => 'General', 'image' => 'cityarea.png'],
        ];

        foreach ($functions as $function) {
            CityFunction::create($function);
        }
    }
}
