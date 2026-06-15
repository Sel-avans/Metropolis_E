<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CityFunction;
use App\Models\EventRoute;
use App\Models\GridCell;
use App\Models\SimulationEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GridRoutePointProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withMiddleware();
    }

    private function planner(): User
    {
        return User::factory()->create(['role' => UserRole::City_planner]);
    }

    public function test_route_point_functions_can_be_removed_from_grid_via_api(): void
    {
        $road = CityFunction::factory()->create(['name' => 'Road', 'category' => 'mobility']);
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = SimulationEvent::create([
            'name' => 'City Event',
            'description' => 'Test',
            'type' => 'one-off',
            'start_moment' => '2026-06-01 10:00:00',
            'end_moment' => '2026-06-01 18:00:00',
        ]);

        $startCell = GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => $road->id]);
        $endCell = GridCell::factory()->create(['row' => 2, 'col' => 4, 'function_id' => $store->id]);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 3,
            'end_row' => 2,
            'end_col' => 4,
            'end_function_id' => $store->id,
        ]);

        $this->actingAs($this->planner())
            ->deleteJson("/grid/cell/{$startCell->id}/function")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->actingAs($this->planner())
            ->deleteJson("/grid/cell/{$endCell->id}/function")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('grid_cells', [
            'id' => $startCell->id,
            'function_id' => null,
        ]);
        $this->assertDatabaseHas('grid_cells', [
            'id' => $endCell->id,
            'function_id' => null,
        ]);
    }
}
