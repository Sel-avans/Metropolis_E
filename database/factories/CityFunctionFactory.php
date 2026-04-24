<?php

namespace Database\Factories;

use App\Models\CityFunction;
use Illuminate\Database\Eloquent\Factories\Factory;

class CityFunctionFactory extends Factory
{
    protected $model = CityFunction::class;

    public function definition()
    {
        return [
            'name' => $this->faker->word(),
            'category' => $this->faker->word(),
            'image' => null,
        ];
    }
}
