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

class EventRouteEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withMiddleware();
    }

    private function createRoad(): CityFunction
    {
        return CityFunction::factory()->create(['name' => 'Road', 'category' => 'mobility']);
    }

    private function createEventWithFunctions(array $functionIds): SimulationEvent
    {
        $event = SimulationEvent::create([
            'name' => 'City Event',
            'description' => 'Test',
            'type' => 'one-off',
            'start_moment' => '2026-06-01 10:00:00',
            'end_moment' => '2026-06-01 18:00:00',
        ]);

        foreach ($functionIds as $functionId) {
            EventEffect::create([
                'simulation_event_id' => $event->id,
                'city_function_id' => $functionId,
                'modifier' => 0,
            ]);
        }

        return $event;
    }

    private function planner(): User
    {
        return User::factory()->create(['role' => UserRole::City_planner]);
    }

    public function test_endpoint_context_lists_assigned_functions_with_placements(): void
    {
        $road = $this->createRoad();
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $park = CityFunction::factory()->create(['name' => 'Park']);
        $event = $this->createEventWithFunctions([$store->id, $park->id]);

        GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 4, 'function_id' => $store->id]);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 3,
        ]);

        $response = $this->actingAs($this->planner())
            ->getJson("/event-routes/{$event->id}/endpoint-context");

        $response->assertOk();
        $response->assertJsonPath('assigned_functions.0.name', 'Store');
        $response->assertJsonPath('assigned_functions.0.placements.0.row', 2);
        $response->assertJsonPath('assigned_functions.0.placements.0.col', 4);
        $response->assertJsonPath('assigned_functions.1.name', 'Park');
        $response->assertJsonPath('assigned_functions.1.placements', []);
    }

    public function test_set_endpoint_for_single_placed_function(): void
    {
        $road = $this->createRoad();
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = $this->createEventWithFunctions([$store->id]);

        GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 4, 'function_id' => $store->id]);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 3,
        ]);

        $response = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/endpoint", [
                'function_id' => $store->id,
            ]);

        $response->assertOk();
        $response->assertJsonPath('route.end_row', 2);
        $response->assertJsonPath('route.end_col', 4);
        $response->assertJsonPath('route.end_function_id', $store->id);
    }

    public function test_set_endpoint_fails_when_function_not_on_grid(): void
    {
        $road = $this->createRoad();
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = $this->createEventWithFunctions([$store->id]);

        GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => $road->id]);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 3,
        ]);

        $response = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/endpoint", [
                'function_id' => $store->id,
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => 'function_not_on_grid',
        ]);
    }

    public function test_set_endpoint_requires_cell_choice_when_multiple_placements(): void
    {
        $road = $this->createRoad();
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = $this->createEventWithFunctions([$store->id]);

        GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 2, 'function_id' => $store->id]);
        GridCell::factory()->create(['row' => 3, 'col' => 2, 'function_id' => $store->id]);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 3,
        ]);

        $response = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/endpoint", [
                'function_id' => $store->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'endpoint_choice_required');
        $response->assertJsonCount(2, 'placements');
    }

    public function test_set_endpoint_with_explicit_cell(): void
    {
        $road = $this->createRoad();
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = $this->createEventWithFunctions([$store->id]);

        GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 2, 'function_id' => $store->id]);
        GridCell::factory()->create(['row' => 3, 'col' => 2, 'function_id' => $store->id]);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 3,
        ]);

        $response = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/endpoint", [
                'function_id' => $store->id,
                'row' => 3,
                'col' => 2,
            ]);

        $response->assertOk();
        $response->assertJsonPath('route.end_row', 3);
        $response->assertJsonPath('route.end_col', 2);
    }

    public function test_grid_page_shows_set_end_point_button(): void
    {
        $planner = User::factory()->create(['role' => UserRole::City_planner]);

        $response = $this->actingAs($planner)->get('/grid');

        $response->assertOk();
        $response->assertSee('Set end point');
        $response->assertSee('Delete end point');
        $response->assertSee('Delete start point');
    }

    public function test_city_planner_can_delete_endpoint(): void
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

        $response = $this->actingAs($this->planner())
            ->deleteJson("/event-routes/{$event->id}/endpoint");

        $response->assertOk();
        $this->assertDatabaseHas('event_routes', [
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 3,
            'end_row' => null,
            'end_col' => null,
            'end_function_id' => null,
        ]);
    }
}
