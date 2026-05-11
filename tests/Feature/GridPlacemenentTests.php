<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\GridCell;
use App\Models\CityFunction;

class GridPlacementTests extends TestCase
{
    public function test_forbidden_combination_returns_error()
    {
        $school = CityFunction::factory()->create(['name' => 'School']);
        $tank = CityFunction::factory()->create(['name' => 'Tankstation']);

        GridCell::factory()->create(['row' => 1, 'col' => 1, 'function_id' => $school->id]);

        $response = $this->post('/grid/update', [
            'new_row' => 1,
            'new_col' => 2,
            'function' => 'Tankstation'
        ]);

        $response->assertJson(['warning' => true]);
    }
}
