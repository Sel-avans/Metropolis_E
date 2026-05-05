<?php

namespace Database\Factories;

use App\Models\Effect;
use Illuminate\Database\Eloquent\Factories\Factory;

class EffectFactory extends Factory
{
    protected $model = Effect::class;

    public function definition()
    {
        return [
            'city_function_id' => null,
            'category' => $this->faker->word(),
            'value' => $this->faker->numberBetween(-3, 5),
        ];
    }
}
