<?php
// Tests voor SIM.5

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
        $pol = CityFunction::factory()->create(['name' => 'Politiebureau', 'category' => 'safety']);
        $brand = CityFunction::factory()->create(['name' => 'Brandweerkazerne', 'category' => 'safety']);
        $park = CityFunction::factory()->create(['name' => 'Park', 'category' => 'recreation']);

        Effect::factory()->create(['function_id' => $pol->id, 'category' => 'safety', 'value' => 5]);
        Effect::factory()->create(['function_id' => $pol->id, 'category' => 'mobility', 'value' => -1]);

        Effect::factory()->create(['function_id' => $brand->id, 'category' => 'safety', 'value' => 4]);

        Effect::factory()->create(['function_id' => $park->id, 'category' => 'recreation', 'value' => 3]);
        Effect::factory()->create(['function_id' => $park->id, 'category' => 'environment', 'value' => 2]);

        GridCell::create(['row' => 1, 'col' => 1, 'function_id' => $pol->id]);
        GridCell::create(['row' => 1, 'col' => 2, 'function_id' => $brand->id]);
        GridCell::create(['row' => 2, 'col' => 1, 'function_id' => $park->id]);

        $response = $this->get('/qol/details');

        $response->assertStatus(200)
                 ->assertJson([
                     'total_score' => 15
                 ]);
    }
}