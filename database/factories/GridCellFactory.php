<?php

namespace Database\Factories;

use App\Models\GridCell;
use Illuminate\Database\Eloquent\Factories\Factory;

class GridCellFactory extends Factory
{
    protected $model = GridCell::class;

    public function definition()
    {
        return [
            'row' => $this->faker->numberBetween(1, 3),
            'col' => $this->faker->numberBetween(1, 4),
            'city_function_id' => null,
        ];
    }
}
        