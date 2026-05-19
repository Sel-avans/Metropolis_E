<?php

namespace Tests\Feature\Grid;

use Tests\TestCase;
use App\Models\GridCell;
use App\Models\CityFunction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DeleteFunctionViaButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_delete_function_from_cell_via_delete_button()
    {
        $function = CityFunction::factory()->create();
        $cell = GridCell::factory()->create([
            'row' => 1,
            'col' => 1,
            'function_id' => $function->id
        ]);

        $response = $this->delete("/grid/cell/{$cell->id}/function");

        $response->assertStatus(200);
        $this->assertDatabaseHas('grid_cells', [
            'id' => $cell->id,
            'function_id' => null
        ]);
    }
}
