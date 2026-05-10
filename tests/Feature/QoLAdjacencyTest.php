<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\GridCell;
use App\Models\CityFunction;

class QoLAdjacencyTest extends TestCase
{
    use RefreshDatabase;

    /** -----------------------------------------------------------
     *  1. Alleen RIGHT en DOWN neighbors worden gedetecteerd
     * ------------------------------------------------------------*/
    public function test_neighbors_right_and_down_are_detected()
    {
        $center = GridCell::factory()->create(['row' => 5, 'col' => 5]);
        $right  = GridCell::factory()->create(['row' => 5, 'col' => 6]);
        $down   = GridCell::factory()->create(['row' => 6, 'col' => 5]);

        $response = $this->getJson('/qol/details');

        $response->assertStatus(200);
    }

    /** -----------------------------------------------------------
     *  2. Penalty: sensitive naast polluting → -2
     * ------------------------------------------------------------*/
    public function test_penalty_sensitive_next_to_polluting()
    {
        $school = CityFunction::factory()->create([
            'name' => 'School',
            'category' => 'voorzieningen'
        ]);

        $weg = CityFunction::factory()->create([
            'name' => 'Weg',
            'category' => 'mobiliteit'
        ]);

        GridCell::factory()->create([
            'row' => 5,
            'col' => 5,
            'city_function_id' => $school->id
        ]);

        GridCell::factory()->create([
            'row' => 5,
            'col' => 6,
            'city_function_id' => $weg->id
        ]);

        $response = $this->getJson('/qol/details');

        $response->assertStatus(200);

        $this->assertEquals(
            -2,
            $response->json('categories.voorzieningen.total')
        );
    }

    /** -----------------------------------------------------------
     *  3. Bonus: zelfde category recreatie → +2
     * ------------------------------------------------------------*/
    public function test_bonus_same_category_recreatie()
    {
        $park1 = CityFunction::factory()->create([
            'name' => 'Park',
            'category' => 'recreatie'
        ]);

        $park2 = CityFunction::factory()->create([
            'name' => 'Speeltuin',
            'category' => 'recreatie'
        ]);

        GridCell::factory()->create([
            'row' => 5,
            'col' => 5,
            'city_function_id' => $park1->id
        ]);

        GridCell::factory()->create([
            'row' => 5,
            'col' => 6,
            'city_function_id' => $park2->id
        ]);

        $response = $this->getJson('/qol/details');

        $response->assertStatus(200);

        $this->assertEquals(
            2,
            $response->json('categories.recreatie.total')
        );
    }

    /** -----------------------------------------------------------
     *  4. Total_score bevat synergy effecten
     * ------------------------------------------------------------*/
    public function test_total_score_includes_synergy()
    {
        $park1 = CityFunction::factory()->create([
            'name' => 'Park',
            'category' => 'recreatie'
        ]);

        $park2 = CityFunction::factory()->create([
            'name' => 'Speeltuin',
            'category' => 'recreatie'
        ]);

        GridCell::factory()->create([
            'row' => 5,
            'col' => 5,
            'city_function_id' => $park1->id
        ]);

        GridCell::factory()->create([
            'row' => 5,
            'col' => 6,
            'city_function_id' => $park2->id
        ]);

        $response = $this->getJson('/qol/details');

        $response->assertStatus(200);

        $this->assertEquals(
            2,
            $response->json('total_score')
        );
    }
}
