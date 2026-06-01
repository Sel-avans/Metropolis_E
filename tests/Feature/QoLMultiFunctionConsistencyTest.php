<?php

namespace Tests\Feature;

use App\Models\CityFunction;
use App\Models\Effect;
use App\Models\GridCell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QoLMultiFunctionConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_adjacent_police_stations_match_global_and_hover_totals(): void
    {
        $police = CityFunction::factory()->create([
            'name' => 'Police Station',
            'category' => 'safety',
        ]);

        foreach (['safety' => 5, 'recreation' => 1, 'environment' => 1, 'mobility' => 2] as $category => $value) {
            Effect::factory()->create([
                'function_id' => $police->id,
                'simulation_event_id' => null,
                'category' => $category,
                'value' => $value,
            ]);
        }

        GridCell::factory()->create(['row' => 1, 'col' => 1, 'function_id' => $police->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 2, 'function_id' => $police->id]);

        $global = $this->getJson('/qol/details')->assertOk()->json();

        $this->assertSame(20, $global['total_score']);
        $this->assertSame(12, $global['categories']['Safety']['total']);

        $hoverLeft = $this->getJson('/qol/cell/1/1')->assertOk()->json();
        $hoverRight = $this->getJson('/qol/cell/1/2')->assertOk()->json();

        $this->assertSame(11, $hoverLeft['total_score']);
        $this->assertSame(9, $hoverRight['total_score']);
        $this->assertSame(
            $hoverLeft['total_score'] + $hoverRight['total_score'],
            $global['total_score']
        );
    }
}
