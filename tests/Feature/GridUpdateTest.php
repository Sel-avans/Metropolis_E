<?php

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
}
