<?php
// Tests voor SIM.3

namespace Tests\Feature\Grid;

use App\Enums\UserRole;
use App\Models\User;
use Tests\TestCase;
use App\Models\GridCell;
use App\Models\CityFunction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DeleteFunctionViaDragOffTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withMiddleware();

        $this->user = User::factory()->create([
            'role' => UserRole::Administrator->value,
        ]);

        $this->actingAs($this->user);
    }

    public function test_user_can_delete_function_by_dragging_it_off_the_grid()
    {
        $function = CityFunction::factory()->create();
        $cell = GridCell::factory()->create([
            'row' => 2,
            'col' => 2,
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
