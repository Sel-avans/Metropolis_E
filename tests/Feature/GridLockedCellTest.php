<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CityFunction;
use App\Models\GridCell;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GridLockedCellTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_add_function_to_empty_locked_cell(): void
    {
        $function = CityFunction::factory()->create();
        $cell = GridCell::factory()->create([
            'row' => 1,
            'col' => 1,
            'function_id' => null,
            'is_approved' => true,
        ]);

        $response = $this->actingAs(User::factory()->create([
            'role' => UserRole::City_planner,
        ]))->postJson('/grid/update', [
            'new_row' => $cell->row,
            'new_col' => $cell->col,
            'function_id' => $function->id,
        ]);

        $response->assertForbidden();
        $response->assertJson([
            'success' => false,
            'error' => 'cell_locked',
            'message' => "You can't add a function in this area",
        ]);
    }

    public function test_cannot_replace_function_in_locked_cell(): void
    {
        $existingFunction = CityFunction::factory()->create();
        $newFunction = CityFunction::factory()->create();
        $cell = GridCell::factory()->create([
            'row' => 2,
            'col' => 2,
            'function_id' => $existingFunction->id,
            'is_approved' => true,
        ]);

        $response = $this->actingAs(User::factory()->create([
            'role' => UserRole::City_planner,
        ]))->postJson('/grid/update', [
            'new_row' => $cell->row,
            'new_col' => $cell->col,
            'function_id' => $newFunction->id,
        ]);

        $response->assertForbidden();
        $response->assertJson([
            'success' => false,
            'error' => 'cell_locked',
            'message' => "You can't replace the function in this area",
        ]);
    }
}
