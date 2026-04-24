<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\GridCell;
use App\Models\CityFunction;
use App\Models\Effect;
use Illuminate\Foundation\Testing\RefreshDatabase;

class QoLNegativeEffectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_qol_for_school_ziekenhuis_winkel_is_8()
    {
        $school = CityFunction::factory()->create(['name' => 'School']);
        $ziek = CityFunction::factory()->create(['name' => 'Ziekenhuis']);
        $winkel = CityFunction::factory()->create(['name' => 'Winkel']);

        // School
        Effect::factory()->create(['city_function_id' => $school->id, 'category' => 'voorzieningen', 'value' => 4]);
        Effect::factory()->create(['city_function_id' => $school->id, 'category' => 'mobiliteit', 'value' => -1]);

        // Ziekenhuis
        Effect::factory()->create(['city_function_id' => $ziek->id, 'category' => 'gezondheid', 'value' => 5]);
        Effect::factory()->create(['city_function_id' => $ziek->id, 'category' => 'mobiliteit', 'value' => -2]);

        // Winkel
        Effect::factory()->create(['city_function_id' => $winkel->id, 'category' => 'voorzieningen', 'value' => 3]);
        Effect::factory()->create(['city_function_id' => $winkel->id, 'category' => 'mobiliteit', 'value' => -1]);

        // Grid
        GridCell::create(['row' => 1, 'col' => 1, 'city_function_id' => $school->id]);
        GridCell::create(['row' => 1, 'col' => 2, 'city_function_id' => $ziek->id]);
        GridCell::create(['row' => 1, 'col' => 3, 'city_function_id' => $winkel->id]);

        $response = $this->get('/qol/details');

        $response->assertStatus(200)
                 ->assertJson([
                     'total_score' => 8
                 ]);
    }
}
