<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CityFunction;
use App\Models\EventEffect;
use App\Models\EventRoute;
use App\Models\GridCell;
use App\Models\SimulationEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRouteSyncGridMoveTest extends TestCase
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

    private function createRoad(): CityFunction
    {
        return CityFunction::factory()->create(['name' => 'Road', 'category' => 'mobility']);
    }

    public function test_moving_road_start_point_updates_stored_coordinates(): void
    {
        $road = $this->createRoad();
        $event = SimulationEvent::create([
            'name' => 'City Event',
            'description' => 'Test',
            'type' => 'one-off',
            'start_moment' => '2026-06-01 10:00:00',
            'end_moment' => '2026-06-01 18:00:00',
        ]);

        GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => null]);
        GridCell::factory()->create(['row' => 2, 'col' => 4, 'function_id' => $road->id]);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 3,
        ]);

        $response = $this->actingAs($this->planner())->postJson('/event-routes/sync-grid-move', [
            'old_row' => 2,
            'old_col' => 3,
            'new_row' => 2,
            'new_col' => 4,
            'function_id' => $road->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('event_routes', [
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 4,
        ]);
    }

    public function test_moving_road_onto_endpoint_cell_clears_invalid_endpoint(): void
    {
        $road = $this->createRoad();
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = SimulationEvent::create([
            'name' => 'City Event',
            'description' => 'Test',
            'type' => 'one-off',
            'start_moment' => '2026-06-01 10:00:00',
            'end_moment' => '2026-06-01 18:00:00',
        ]);

        EventEffect::create([
            'simulation_event_id' => $event->id,
            'city_function_id' => $store->id,
            'modifier' => 0,
        ]);

        GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => null]);
        GridCell::factory()->create(['row' => 2, 'col' => 4, 'function_id' => $road->id]);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 3,
            'end_row' => 2,
            'end_col' => 4,
            'end_function_id' => $store->id,
        ]);

        GridCell::where('row', 2)->where('col', 3)->update(['function_id' => null]);
        GridCell::where('row', 2)->where('col', 4)->update(['function_id' => $road->id]);

        $response = $this->actingAs($this->planner())->postJson('/event-routes/sync-grid-move', [
            'old_row' => 2,
            'old_col' => 3,
            'new_row' => 2,
            'new_col' => 4,
            'function_id' => $road->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('event_routes', [
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 4,
            'end_row' => null,
            'end_col' => null,
            'end_function_id' => $store->id,
        ]);
    }

    public function test_swapping_start_and_end_via_two_moves_keeps_both_points(): void
    {
        $road = $this->createRoad();
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = SimulationEvent::create([
            'name' => 'City Event',
            'description' => 'Test',
            'type' => 'one-off',
            'start_moment' => '2026-06-01 10:00:00',
            'end_moment' => '2026-06-01 18:00:00',
        ]);

        EventEffect::create([
            'simulation_event_id' => $event->id,
            'city_function_id' => $store->id,
            'modifier' => 0,
        ]);

        GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 4, 'function_id' => $store->id]);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 3,
            'end_row' => 2,
            'end_col' => 4,
            'end_function_id' => $store->id,
        ]);

        $planner = $this->planner();

        GridCell::where('row', 2)->where('col', 3)->update(['function_id' => null]);
        GridCell::where('row', 2)->where('col', 4)->update(['function_id' => $road->id]);

        $this->actingAs($planner)->postJson('/event-routes/sync-grid-move', [
            'old_row' => 2,
            'old_col' => 3,
            'new_row' => 2,
            'new_col' => 4,
            'function_id' => $road->id,
        ])->assertOk();

        GridCell::where('row', 2)->where('col', 3)->update(['function_id' => $store->id]);

        $this->actingAs($planner)->postJson('/event-routes/sync-grid-move', [
            'old_row' => null,
            'old_col' => null,
            'new_row' => 2,
            'new_col' => 3,
            'function_id' => $store->id,
        ])->assertOk();

        $this->assertDatabaseHas('event_routes', [
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 4,
            'end_row' => 2,
            'end_col' => 3,
            'end_function_id' => $store->id,
        ]);
    }

    public function test_moving_endpoint_function_updates_stored_coordinates(): void
    {
        $road = $this->createRoad();
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = SimulationEvent::create([
            'name' => 'City Event',
            'description' => 'Test',
            'type' => 'one-off',
            'start_moment' => '2026-06-01 10:00:00',
            'end_moment' => '2026-06-01 18:00:00',
        ]);

        GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 4, 'function_id' => null]);
        GridCell::factory()->create(['row' => 1, 'col' => 4, 'function_id' => $store->id]);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 3,
            'end_row' => 2,
            'end_col' => 4,
            'end_function_id' => $store->id,
        ]);

        $response = $this->actingAs($this->planner())->postJson('/event-routes/sync-grid-move', [
            'old_row' => 2,
            'old_col' => 4,
            'new_row' => 1,
            'new_col' => 4,
            'function_id' => $store->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('event_routes', [
            'simulation_event_id' => $event->id,
            'end_row' => 1,
            'end_col' => 4,
            'end_function_id' => $store->id,
        ]);
    }

    public function test_swapping_endpoint_with_occupied_cell_keeps_endpoint_coordinates(): void
    {
        $road = $this->createRoad();
        $bikePath = CityFunction::factory()->create(['name' => 'Bicycle path']);
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = SimulationEvent::create([
            'name' => 'City Event',
            'description' => 'Test',
            'type' => 'one-off',
            'start_moment' => '2026-06-01 10:00:00',
            'end_moment' => '2026-06-01 18:00:00',
        ]);

        EventEffect::create([
            'simulation_event_id' => $event->id,
            'city_function_id' => $bikePath->id,
            'modifier' => 0,
        ]);

        GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 4, 'function_id' => $bikePath->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 4, 'function_id' => $store->id]);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 3,
            'end_row' => 1,
            'end_col' => 4,
            'end_function_id' => $bikePath->id,
        ]);

        $planner = $this->planner();

        GridCell::where('row', 1)->where('col', 4)->update(['function_id' => null]);
        GridCell::where('row', 2)->where('col', 4)->update(['function_id' => $bikePath->id]);
        GridCell::where('row', 1)->where('col', 4)->update(['function_id' => $store->id]);

        $this->actingAs($planner)->postJson('/event-routes/sync-grid-move', [
            'old_row' => 1,
            'old_col' => 4,
            'new_row' => 2,
            'new_col' => 4,
            'function_id' => $bikePath->id,
        ])->assertOk();

        $this->actingAs($planner)->postJson('/event-routes/sync-grid-move', [
            'old_row' => 2,
            'old_col' => 4,
            'new_row' => 1,
            'new_col' => 4,
            'function_id' => $store->id,
        ])->assertOk();

        $this->assertDatabaseHas('event_routes', [
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 3,
            'end_row' => 2,
            'end_col' => 4,
            'end_function_id' => $bikePath->id,
        ]);
    }

    public function test_removing_endpoint_function_from_grid_clears_stored_coordinates(): void
    {
        $road = $this->createRoad();
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = SimulationEvent::create([
            'name' => 'City Event',
            'description' => 'Test',
            'type' => 'one-off',
            'start_moment' => '2026-06-01 10:00:00',
            'end_moment' => '2026-06-01 18:00:00',
        ]);

        EventEffect::create([
            'simulation_event_id' => $event->id,
            'city_function_id' => $store->id,
            'modifier' => 0,
        ]);

        GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 4, 'function_id' => null]);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 3,
            'end_row' => 1,
            'end_col' => 4,
            'end_function_id' => $store->id,
        ]);

        $response = $this->actingAs($this->planner())->postJson('/event-routes/sync-grid-remove', [
            'row' => 1,
            'col' => 4,
            'function_id' => $store->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('event_routes', [
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 3,
            'end_row' => null,
            'end_col' => null,
            'end_function_id' => $store->id,
        ]);
    }
}
