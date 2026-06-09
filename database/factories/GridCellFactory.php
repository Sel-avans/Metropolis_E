<?php

namespace Database\Factories;

use App\Models\GridCell;
use Illuminate\Database\Eloquent\Factories\Factory;

class GridCellFactory extends Factory
{
    protected $model = GridCell::class;

    public function definition(): array
    {
        return [
            'row' => $this->faker->numberBetween(1, 4),
            'col' => $this->faker->numberBetween(1, 3),
            'function_id' => null,
            'is_approved' => false,
        ];
    }
}