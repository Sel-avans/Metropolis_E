<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Grid;

class GridController extends Controller
{
    public function index()
    {
        $grid = new Grid(12);
        $html = $grid->paintGrid("City Grid");

        $functions = [
            'education' => [
                ['name' => 'School', 'image' => 'school.png'],
                ['name' => 'Daycare', 'image' => 'daycare.png'],
                ['name' => 'University', 'image' => 'student.png'],
            ],
            'health' => [
                ['name' => 'Hospital', 'image' => 'hospital.png'],
                ['name' => 'Doctor', 'image' => 'doctor.png'],
                ['name' => 'Senior Center', 'image' => 'senior.png'],
            ],
            'public' => [
                ['name' => 'Police Station', 'image' => 'police.png'],
                ['name' => 'Fire Station', 'image' => 'firestation.png'],
                ['name' => 'Museum', 'image' => 'museum.png'],
                ['name' => 'Theater', 'image' => 'theater.png'],
            ],
            'commerce' => [
                ['name' => 'Supermarket', 'image' => 'supermarket.png'],
                ['name' => 'Mall', 'image' => 'mall.png'],
                ['name' => 'Restaurant', 'image' => 'restaurant.png'],
                ['name' => 'Warehouse', 'image' => 'warehouse.png'],
                ['name' => 'Factory', 'image' => 'factory.png'],
                ['name' => 'Office', 'image' => 'office.png'],
            ],
            'transport' => [
                ['name' => 'Bus Station', 'image' => 'bus.png'],
                ['name' => 'Train Station', 'image' => 'train.png'],
                ['name' => 'Parking', 'image' => 'parking.png'],
                ['name' => 'Road', 'image' => 'road.png'],
            ],
            'recreation' => [
                ['name' => 'Park', 'image' => 'park.png'],
                ['name' => 'Playground', 'image' => 'playground.png'],
                ['name' => 'Sports Center', 'image' => 'sports.png'],
                ['name' => 'Stadium', 'image' => 'stadium.png'],
                ['name' => 'Pool', 'image' => 'pool.png'],
                ['name' => 'Pond', 'image' => 'pond.png'],
                ['name' => 'Forest', 'image' => 'forest.png'],
                ['name' => 'Green Zone', 'image' => 'greenzone.png'],
                ['name' => 'River', 'image' => 'river.png'],
            ],
            'residential' => [
                ['name' => 'Apartment', 'image' => 'apartment.png'],
                ['name' => 'Detached House', 'image' => 'detached.png'],
                ['name' => 'Row House', 'image' => 'rowhouse.png'],
                ['name' => 'Villa', 'image' => 'villa.png'],
                ['name' => 'City Area', 'image' => 'cityarea.png'],
            ],
            'infrastructure' => [
                ['name' => 'Power Plant', 'image' => 'powerplant.png'],
                ['name' => 'Bike Path', 'image' => 'bikepath.png'],
            ],
        ];

        return view('gridView', compact('html', 'functions'));
    }
}
