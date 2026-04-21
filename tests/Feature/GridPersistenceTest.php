<?php

namespace Tests\Feature;

use App\Models\CityFunction;
use App\Models\GridState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GridPersistenceTest extends TestCase
{
    use RefreshDatabase; // Resets the database after each test

    /**
     * Subtask: Drag & Drop from library.
     */
    public function test_can_save_new_grid_cell(): void
    {
        // 1. Create a function
        $function = CityFunction::create([
            'name' => 'Kantoor', 'category' => 'Werk', 'image' => 'kantoor.jpg'
        ]);

        // 2. Simulate placing it on the grid
        $response = $this->postJson('/save-cell', [
            'x' => 5,
            'y' => 5,
            'city_function_id' => $function->id
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('grid_states', ['x' => 5, 'y' => 5]);
    }

    /**
     * Subtask: Moving an item and cleaning up old position.
     */
    public function test_moving_item_deletes_old_record(): void
    {
        $function = CityFunction::create(['name' => 'Park', 'category' => 'Groen', 'image' => 'park.jpg']);
        
        // 1. Setup initial position
        GridState::create(['x' => 1, 'y' => 1, 'city_function_id' => $function->id]);

        // 2. Move it to [2, 2] and send the old coordinates [1, 1]
        $this->postJson('/save-cell', [
            'x' => 2,
            'y' => 2,
            'city_function_id' => $function->id,
            'oldX' => 1,
            'oldY' => 1
        ]);

        // 3. Assert: New exists, old is GONE
        $this->assertDatabaseMissing('grid_states', ['x' => 1, 'y' => 1]);
        $this->assertDatabaseHas('grid_states', ['x' => 2, 'y' => 2]);
    }
}