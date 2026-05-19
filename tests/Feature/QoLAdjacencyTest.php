<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\GridCell;
use App\Models\CityFunction;

class QoLAdjacencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_neighbors_right_and_down_are_detected()
    {
        $center = GridCell::factory()->create(['row' => 5, 'col' => 5]);
        $right  = GridCell::factory()->create(['row' => 5, 'col' => 6]);
        $down   = GridCell::factory()->create(['row' => 6, 'col' => 5]);

        $response = $this->getJson('/qol/details');

        $response->assertStatus(200);
    }

    public function test_penalty_sensitive_next_to_polluting()
    {
        $school = CityFunction::factory()->create([
            'name' => 'School',
            'category' => 'amenities',
            'sensitivity' => 'sensitive'
        ]);

        $weg = CityFunction::factory()->create([
            'name' => 'Weg',
            'category' => 'mobility',
            'pollution' => 'polluting'
        ]);

        GridCell::factory()->create([
            'row' => 5,
            'col' => 5,
            'function_id' => $school->id
        ]);

        GridCell::factory()->create([
            'row' => 5,
            'col' => 6,
            'function_id' => $weg->id
        ]);

        $response = $this->getJson('/qol/details');

        $response->assertStatus(200);

        $this->assertEquals(
            -2,
            $response->json('categories.amenities.total')
        );
    }

    public function test_bonus_same_category_recreatie()
    {
        $park1 = CityFunction::factory()->create([
            'name' => 'Park',
            'category' => 'recreation'
        ]);

        $park2 = CityFunction::factory()->create([
            'name' => 'Speeltuin',
            'category' => 'recreation'
        ]);

        GridCell::factory()->create([
            'row' => 5,
            'col' => 5,
            'function_id' => $park1->id
        ]);

        GridCell::factory()->create([
            'row' => 5,
            'col' => 6,
            'function_id' => $park2->id
        ]);

        $response = $this->getJson('/qol/details');

        $response->assertStatus(200);

        $this->assertEquals(
            2,
            $response->json('categories.recreation.total')
        );
    }

    public function test_total_score_includes_synergy()
    {
        $park1 = CityFunction::factory()->create([
            'name' => 'Park',
            'category' => 'recreation'
        ]);

        $park2 = CityFunction::factory()->create([
            'name' => 'Speeltuin',
            'category' => 'recreation'
        ]);

        GridCell::factory()->create([
            'row' => 5,
            'col' => 5,
            'function_id' => $park1->id
        ]);

        GridCell::factory()->create([
            'row' => 5,
            'col' => 6,
            'function_id' => $park2->id
        ]);

        $response = $this->getJson('/qol/details');

        $response->assertStatus(200);

        $this->assertEquals(
            2,
            $response->json('total_score')
        );
    }
}