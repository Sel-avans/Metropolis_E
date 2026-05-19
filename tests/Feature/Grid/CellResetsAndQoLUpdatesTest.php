<?php

namespace Tests\Feature\Grid;

use Tests\TestCase;
use App\Models\GridCell;
use App\Models\CityFunction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CellResetsAndQoLUpdatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_removing_a_function_resets_cell_and_updates_qol()
    {
        $function = CityFunction::factory()->create();
        $cell = GridCell::factory()->create([
            'row' => 3,
            'col' => 1,
            'function_id' => $function->id
        ]);

        $this->get('/qol/details')->assertStatus(200);

        $this->delete("/grid/cell/{$cell->id}/function")
             ->assertStatus(200);

        $this->assertDatabaseHas('grid_cells', [
            'id' => $cell->id,
            'function_id' => null
        ]);

        $this->get('/qol/details')->assertStatus(200);
    }
}
