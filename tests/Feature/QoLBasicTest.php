<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\GridCell;
use App\Models\CityFunction;
use App\Models\Effect;
use Illuminate\Foundation\Testing\RefreshDatabase;

class QoLBasicTest extends TestCase
{
    use RefreshDatabase;

    public function test_qol_for_three_basic_functions_is_correct()
    {
        $pol = CityFunction::factory()->create(['name' => 'Politiebureau']);
        $brand = CityFunction::factory()->create(['name' => 'Brandweerkazerne']);
        $park = CityFunction::factory()->create(['name' => 'Park']);

        Effect::factory()->create(['city_function_id' => $pol->id, 'category' => 'veiligheid', 'value' => 5]);
        Effect::factory()->create(['city_function_id' => $pol->id, 'category' => 'mobiliteit', 'value' => -1]);

        Effect::factory()->create(['city_function_id' => $brand->id, 'category' => 'veiligheid', 'value' => 4]);

        Effect::factory()->create(['city_function_id' => $park->id, 'category' => 'recreatie', 'value' => 3]);
        Effect::factory()->create(['city_function_id' => $park->id, 'category' => 'milieukwaliteit', 'value' => 2]);

        GridCell::create(['row' => 1, 'col' => 1, 'city_function_id' => $pol->id]);
        GridCell::create(['row' => 1, 'col' => 2, 'city_function_id' => $brand->id]);
        GridCell::create(['row' => 2, 'col' => 1, 'city_function_id' => $park->id]);

        $response = $this->get('/qol/details');

        $response->assertStatus(200)
                 ->assertJson([
                     'total_score' => 13
                 ]);
    }
}
