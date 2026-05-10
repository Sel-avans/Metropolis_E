<?php

namespace Database\Factories;

use App\Models\AdjacencyRule;
use App\Models\CityFunction;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdjacencyRuleFactory extends Factory
{
    protected $model = AdjacencyRule::class;

    public function definition()
    {
        return [
            'function_a' => CityFunction::factory(),
            'function_b' => CityFunction::factory(),
            'type' => $this->faker->randomElement(['bonus', 'penalty', 'forbidden']),
            'value' => $this->faker->numberBetween(-5, 5),
        ];
    }
}
