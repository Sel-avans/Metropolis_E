<?php
// Tests voor Sim.10? unknown others, Persistence?
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\GridCell;
use App\Models\CityFunction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GridUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_a_function_to_a_grid_cell()
    {
        $func = CityFunction::factory()->create([
            'name' => 'Politiebureau',
            'category' => 'Veiligheid',
        ]);

        $response = $this->post('/grid/update', [
            'new_row' => 1,
            'new_col' => 1,
            'function_id' => $func->id
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseHas('grid_cells', [
            'row' => 1,
            'col' => 1,
            'function_id' => $func->id
        ]);
    }

    public function test_it_swaps_two_grid_functions_instead_of_deleting_the_displaced_one(): void
    {
        $functionA = CityFunction::factory()->create(['name' => 'School A']);
        $functionB = CityFunction::factory()->create(['name' => 'Park B']);

        GridCell::factory()->create(['row' => 1, 'col' => 1, 'function_id' => $functionA->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 2, 'function_id' => $functionB->id]);

        $response = $this->postJson('/grid/update', [
            'old_row' => 1,
            'old_col' => 1,
            'new_row' => 2,
            'new_col' => 2,
            'function_id' => $functionA->id,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('grid_cells', [
            'row' => 2,
            'col' => 2,
            'function_id' => $functionA->id,
        ]);
        $this->assertDatabaseHas('grid_cells', [
            'row' => 1,
            'col' => 1,
            'function_id' => $functionB->id,
        ]);
    }
}
