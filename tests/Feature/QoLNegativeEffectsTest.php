<?php
// Tests voor Sim.5

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
        $school = CityFunction::factory()->create(['name' => 'School', 'category' => 'amenities']);
        $ziek = CityFunction::factory()->create(['name' => 'Ziekenhuis', 'category' => 'amenities']);
        $winkel = CityFunction::factory()->create(['name' => 'Winkel', 'category' => 'amenities']);

        Effect::factory()->create(['function_id' => $school->id, 'category' => 'amenities', 'value' => 4]);
        Effect::factory()->create(['function_id' => $school->id, 'category' => 'mobility', 'value' => -1]);

        Effect::factory()->create(['function_id' => $ziek->id, 'category' => 'amenities', 'value' => 5]);
        Effect::factory()->create(['function_id' => $ziek->id, 'category' => 'mobility', 'value' => -2]);

        Effect::factory()->create(['function_id' => $winkel->id, 'category' => 'amenities', 'value' => 3]);
        Effect::factory()->create(['function_id' => $winkel->id, 'category' => 'mobility', 'value' => -1]);

        GridCell::create(['row' => 1, 'col' => 1, 'function_id' => $school->id]);
        GridCell::create(['row' => 1, 'col' => 2, 'function_id' => $ziek->id]);
        GridCell::create(['row' => 1, 'col' => 3, 'function_id' => $winkel->id]);

        $response = $this->get('/qol/details');

        $response->assertStatus(200)
                 ->assertJson([
                     'total_score' => 12
                 ]);
    }
}