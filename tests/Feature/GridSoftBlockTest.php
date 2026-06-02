<?php
// Tests voor Bes.3

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\CityFunction;
use App\Models\GridCell;
use App\Models\AdjacencyRule;

class GridSoftBlockTest extends TestCase
{
    // Reset the database after each test to ensure a clean state
    use RefreshDatabase;

    public function test_placement_returns_soft_block_when_rule_is_violated()
    {
        // 1. Arrange: Set up the necessary models
        $hospital = CityFunction::create(['name' => 'Ziekenhuis', 'category' => 'voorzieningen']);
        $gasStation = CityFunction::create(['name' => 'Tankstation', 'category' => 'mobiliteit']);

        // Place the Hospital on the grid at row 1, col 1
        GridCell::create(['row' => 1, 'col' => 1, 'function_id' => $hospital->id]);

        // Create a forbidden adjacency rule between Hospital and Gas Station
        AdjacencyRule::create([
            'function_a' => $hospital->id,
            'function_b' => $gasStation->id,
            'type' => 'forbidden',
            'value' => 0
        ]);

        // 2. Act: Attempt to place the Gas Station right next to the Hospital (row 1, col 2)
        // We are NOT sending the 'force' parameter here
        $response = $this->postJson('/grid/update', [
            'new_row' => 1,
            'new_col' => 2,
            'function_id' => $gasStation->id,
            'force' => false
        ]);

        // 3. Assert: The server should block this and return a 409 Conflict status
        $response->assertStatus(409)
                 ->assertJson([
                     'success' => false,
                 ]);
                 
        // Verify the database does NOT contain the Gas Station
        $this->assertDatabaseMissing('grid_cells', [
            'row' => 1,
            'col' => 2,
            'function_id' => $gasStation->id,
        ]);
    }

    public function test_placement_succeeds_when_rule_is_violated_but_forced()
    {
        // 1. Arrange: Set up the necessary models
        $hospital = CityFunction::create(['name' => 'Ziekenhuis', 'category' => 'voorzieningen']);
        $gasStation = CityFunction::create(['name' => 'Tankstation', 'category' => 'mobiliteit']);

        // Place the Hospital on the grid
        GridCell::create(['row' => 1, 'col' => 1, 'function_id' => $hospital->id]);

        // Create the forbidden rule
        AdjacencyRule::create([
            'function_a' => $hospital->id,
            'function_b' => $gasStation->id,
            'type' => 'forbidden',
            'value' => 0
        ]);

        // 2. Act: Attempt the same invalid placement, but this time set 'force' to true
        $response = $this->postJson('/grid/update', [
            'new_row' => 1,
            'new_col' => 2,
            'function_id' => $gasStation->id,
            'force' => true
        ]);

        // 3. Assert: The server should allow this and return a 200 OK status
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                 ]);

        // Verify the Gas Station is actually saved in the database despite the rule
        $this->assertDatabaseHas('grid_cells', [
            'row' => 1,
            'col' => 2,
            'function_id' => $gasStation->id,
        ]);
    }
}