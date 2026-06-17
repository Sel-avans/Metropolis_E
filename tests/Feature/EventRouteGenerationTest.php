<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AdjacencyRule;
use App\Models\CityFunction;
use App\Models\Condition;
use App\Models\EventEffect;
use App\Models\EventRoute;
use App\Models\GridCell;
use App\Models\SimulationEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRouteGenerationTest extends TestCase
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

    private function createEventWithFunction(int $functionId): SimulationEvent
    {
        $event = SimulationEvent::create([
            'name' => 'City Event',
            'description' => 'Test',
            'type' => 'one-off',
            'start_moment' => '2026-06-01 10:00:00',
            'end_moment' => '2026-06-01 18:00:00',
        ]);

        EventEffect::create([
            'simulation_event_id' => $event->id,
            'city_function_id' => $functionId,
            'modifier' => 0,
        ]);

        return $event;
    }

    private function createPlannedRoute(SimulationEvent $event, CityFunction $store): EventRoute
    {
        return EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 1,
            'end_row' => 2,
            'end_col' => 4,
            'end_function_id' => $store->id,
        ]);
    }

    public function test_city_planner_can_generate_route_from_access_road_to_event_location(): void
    {
        $road = $this->createRoad();
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = $this->createEventWithFunction($store->id);
        $route = $this->createPlannedRoute($event, $store);

        GridCell::factory()->create(['row' => 2, 'col' => 1, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 2, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 4, 'function_id' => $store->id]);

        $response = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/generate");

        $response->assertOk();
        $response->assertJsonPath('route.path_cells.0.row', 2);
        $response->assertJsonPath('route.path_cells.0.col', 1);
        $response->assertJsonPath('route.path_cells.3.row', 2);
        $response->assertJsonPath('route.path_cells.3.col', 4);

        $this->assertDatabaseHas('event_routes', [
            'id' => $route->id,
            'start_row' => 2,
            'start_col' => 1,
            'end_row' => 2,
            'end_col' => 4,
        ]);

        $route->refresh();
        $this->assertCount(4, $route->path_cells);
    }

    public function test_route_cannot_be_generated_without_road_path_between_start_and_end(): void
    {
        $road = $this->createRoad();
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = $this->createEventWithFunction($store->id);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 1,
            'end_row' => 2,
            'end_col' => 4,
            'end_function_id' => $store->id,
        ]);

        GridCell::factory()->create(['row' => 2, 'col' => 1, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 4, 'function_id' => $store->id]);

        $response = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/generate");

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'unreachable_event_location');
    }

    public function test_route_cannot_be_drawn_when_detour_requires_empty_cells(): void
    {
        $road = $this->createRoad();
        $gas = CityFunction::factory()->create(['name' => 'Gas Station']);
        $hospital = CityFunction::factory()->create(['name' => 'Hospital']);
        $cinema = CityFunction::factory()->create(['name' => 'Cinema']);
        $park = CityFunction::factory()->create(['name' => 'Park']);
        $mall = CityFunction::factory()->create(['name' => 'Mall']);
        $event = $this->createEventWithFunction($mall->id);

        AdjacencyRule::create([
            'function_a' => min($gas->id, $hospital->id),
            'function_b' => max($gas->id, $hospital->id),
            'type' => 'forbidden',
            'value' => 0,
        ]);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 1,
            'start_col' => 1,
            'end_row' => 1,
            'end_col' => 3,
            'end_function_id' => $mall->id,
        ]);

        GridCell::factory()->create(['row' => 1, 'col' => 1, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 2, 'function_id' => $gas->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 3, 'function_id' => $mall->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 1, 'function_id' => $cinema->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 2, 'function_id' => $park->id]);

        $response = $this->actingAs($this->planner())
            ->getJson('/event-routes');

        $response->assertOk();
        $response->assertJsonPath('routes.0.route_creation.can_draw', false);
        $response->assertJsonPath('routes.0.route_creation.can_generate', false);
    }

    public function test_event_routes_index_blocks_draw_when_no_valid_occupied_route_exists(): void
    {
        $road = $this->createRoad();
        $gas = CityFunction::factory()->create(['name' => 'Gas Station']);
        $hospital = CityFunction::factory()->create(['name' => 'Hospital']);
        $mall = CityFunction::factory()->create(['name' => 'Mall']);
        $event = $this->createEventWithFunction($mall->id);

        AdjacencyRule::create([
            'function_a' => min($gas->id, $hospital->id),
            'function_b' => max($gas->id, $hospital->id),
            'type' => 'forbidden',
            'value' => 0,
        ]);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 1,
            'start_col' => 1,
            'end_row' => 1,
            'end_col' => 3,
            'end_function_id' => $mall->id,
        ]);

        GridCell::factory()->create(['row' => 1, 'col' => 1, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 2, 'function_id' => $gas->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 3, 'function_id' => $mall->id]);

        $response = $this->actingAs($this->planner())
            ->getJson('/event-routes');

        $response->assertOk();
        $response->assertJsonPath('routes.0.route_creation.can_create', false);
        $response->assertJsonPath('routes.0.route_creation.can_generate', false);
        $response->assertJsonPath('routes.0.route_creation.can_draw', false);
        $response->assertJsonPath('routes.0.route_creation.error', 'forbidden_on_route');
    }

    public function test_event_routes_index_marks_unreachable_plans_as_not_creatable(): void
    {
        $road = $this->createRoad();
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = $this->createEventWithFunction($store->id);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 1,
            'start_col' => 1,
            'end_row' => 1,
            'end_col' => 4,
            'end_function_id' => $store->id,
        ]);

        GridCell::factory()->create(['row' => 1, 'col' => 1, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 4, 'function_id' => $store->id]);

        $response = $this->actingAs($this->planner())
            ->getJson('/event-routes');

        $response->assertOk();
        $response->assertJsonPath('routes.0.route_creation.can_create', false);
        $response->assertJsonPath('routes.0.route_creation.can_generate', false);
        $response->assertJsonPath('routes.0.route_creation.can_draw', false);
        $response->assertJsonPath('routes.0.route_creation.error', 'unreachable_event_location');
    }

    public function test_validate_path_cell_rejects_forbidden_function_on_route(): void
    {
        $road = $this->createRoad();
        $gas = CityFunction::factory()->create(['name' => 'Gas Station']);
        $hospital = CityFunction::factory()->create(['name' => 'Hospital']);
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = $this->createEventWithFunction($store->id);

        AdjacencyRule::create([
            'function_a' => min($gas->id, $hospital->id),
            'function_b' => max($gas->id, $hospital->id),
            'type' => 'forbidden',
            'value' => 0,
        ]);

        GridCell::factory()->create(['row' => 1, 'col' => 1, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 2, 'function_id' => $hospital->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 2, 'function_id' => $gas->id]);

        $response = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/validate-path-cell", [
                'from_row' => 1,
                'from_col' => 1,
                'row' => 1,
                'col' => 2,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'forbidden_on_route');
    }

    public function test_validate_path_cell_rejects_forbidden_function_with_neighbour_on_grid(): void
    {
        $road = $this->createRoad();
        $gas = CityFunction::factory()->create(['name' => 'Gas Station']);
        $hospital = CityFunction::factory()->create(['name' => 'Hospital']);
        $event = $this->createEventWithFunction($hospital->id);

        AdjacencyRule::create([
            'function_a' => min($gas->id, $hospital->id),
            'function_b' => max($gas->id, $hospital->id),
            'type' => 'forbidden',
            'value' => 0,
        ]);

        GridCell::factory()->create(['row' => 2, 'col' => 1, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 2, 'function_id' => $hospital->id]);
        GridCell::factory()->create(['row' => 3, 'col' => 2, 'function_id' => $gas->id]);

        $response = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/validate-path-cell", [
                'from_row' => 2,
                'from_col' => 1,
                'row' => 2,
                'col' => 2,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'forbidden_on_route');
    }

    public function test_validate_path_cell_allows_park_when_forbidden_gas_is_only_a_neighbour(): void
    {
        $road = $this->createRoad();
        $gas = CityFunction::factory()->create(['name' => 'Gas Station']);
        $park = CityFunction::factory()->create(['name' => 'Park']);
        $mall = CityFunction::factory()->create(['name' => 'Mall']);
        $event = $this->createEventWithFunction($mall->id);

        Condition::create([
            'function_a' => $park->id,
            'function_b' => $gas->id,
            'type' => 'forbidden',
            'value' => null,
        ]);

        GridCell::factory()->create(['row' => 1, 'col' => 1, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 2, 'function_id' => $park->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 2, 'function_id' => $gas->id]);

        $response = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/validate-path-cell", [
                'from_row' => 1,
                'from_col' => 1,
                'row' => 1,
                'col' => 2,
            ]);

        $response->assertOk();
        $response->assertJsonPath('valid', true);
    }

    public function test_validate_path_cell_allows_function_in_forbidden_pair_without_neighbour_on_grid(): void
    {
        $road = $this->createRoad();
        $gas = CityFunction::factory()->create(['name' => 'Gas Station']);
        $park = CityFunction::factory()->create(['name' => 'Park']);
        $mall = CityFunction::factory()->create(['name' => 'Mall']);
        $event = $this->createEventWithFunction($mall->id);

        Condition::create([
            'function_a' => $park->id,
            'function_b' => $gas->id,
            'type' => 'forbidden',
            'value' => null,
        ]);

        GridCell::factory()->create(['row' => 1, 'col' => 1, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 2, 'function_id' => $park->id]);

        $response = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/validate-path-cell", [
                'from_row' => 1,
                'from_col' => 1,
                'row' => 1,
                'col' => 2,
            ]);

        $response->assertOk();
        $response->assertJsonPath('valid', true);
    }

    public function test_route_cannot_be_generated_when_only_hospital_is_between_start_and_end(): void
    {
        $road = $this->createRoad();
        $gas = CityFunction::factory()->create(['name' => 'Gas Station']);
        $hospital = CityFunction::factory()->create(['name' => 'Hospital']);
        $mall = CityFunction::factory()->create(['name' => 'Mall']);
        $event = $this->createEventWithFunction($mall->id);

        AdjacencyRule::create([
            'function_a' => min($gas->id, $hospital->id),
            'function_b' => max($gas->id, $hospital->id),
            'type' => 'forbidden',
            'value' => 0,
        ]);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 1,
            'start_col' => 1,
            'end_row' => 1,
            'end_col' => 3,
            'end_function_id' => $mall->id,
        ]);

        GridCell::factory()->create(['row' => 1, 'col' => 1, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 2, 'function_id' => $hospital->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 3, 'function_id' => $mall->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 2, 'function_id' => $gas->id]);

        $response = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/generate");

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'forbidden_on_route');
    }

    public function test_route_can_be_generated_through_park_even_when_gas_is_only_a_neighbour(): void
    {
        $road = $this->createRoad();
        $gas = CityFunction::factory()->create(['name' => 'Gas Station']);
        $park = CityFunction::factory()->create(['name' => 'Park']);
        $mall = CityFunction::factory()->create(['name' => 'Mall']);
        $event = $this->createEventWithFunction($mall->id);

        Condition::create([
            'function_a' => $park->id,
            'function_b' => $gas->id,
            'type' => 'forbidden',
            'value' => null,
        ]);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 1,
            'start_col' => 1,
            'end_row' => 1,
            'end_col' => 3,
            'end_function_id' => $mall->id,
        ]);

        GridCell::factory()->create(['row' => 1, 'col' => 1, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 2, 'function_id' => $park->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 3, 'function_id' => $mall->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 2, 'function_id' => $gas->id]);

        $response = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/generate");

        $response->assertOk();
    }

    public function test_validate_path_cell_rejects_forbidden_route_step(): void
    {
        $road = $this->createRoad();
        $gas = CityFunction::factory()->create(['name' => 'Gas Station']);
        $hospital = CityFunction::factory()->create(['name' => 'Hospital']);
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = $this->createEventWithFunction($store->id);

        AdjacencyRule::create([
            'function_a' => min($gas->id, $hospital->id),
            'function_b' => max($gas->id, $hospital->id),
            'type' => 'forbidden',
            'value' => 0,
        ]);

        GridCell::factory()->create(['row' => 2, 'col' => 1, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 2, 'function_id' => $gas->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => $hospital->id]);

        $response = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/validate-path-cell", [
                'from_row' => 2,
                'from_col' => 2,
                'row' => 2,
                'col' => 3,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'forbidden_on_route');
    }

    public function test_validate_path_cell_allows_route_when_forbidden_functions_are_not_on_path(): void
    {
        $road = $this->createRoad();
        $gas = CityFunction::factory()->create(['name' => 'Gas Station']);
        $hospital = CityFunction::factory()->create(['name' => 'Hospital']);
        $police = CityFunction::factory()->create(['name' => 'Police']);
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = $this->createEventWithFunction($store->id);

        AdjacencyRule::create([
            'function_a' => min($gas->id, $hospital->id),
            'function_b' => max($gas->id, $hospital->id),
            'type' => 'forbidden',
            'value' => 0,
        ]);

        GridCell::factory()->create(['row' => 2, 'col' => 1, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 2, 'function_id' => $police->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => $gas->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 4, 'function_id' => $hospital->id]);

        $response = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/validate-path-cell", [
                'from_row' => 2,
                'from_col' => 1,
                'row' => 2,
                'col' => 2,
            ]);

        $response->assertOk();
        $response->assertJsonPath('valid', true);
    }

    public function test_route_cannot_be_generated_through_hospital_between_start_and_end(): void
    {
        $road = $this->createRoad();
        $gas = CityFunction::factory()->create(['name' => 'Gas Station']);
        $hospital = CityFunction::factory()->create(['name' => 'Hospital']);
        $mall = CityFunction::factory()->create(['name' => 'Mall']);
        $event = $this->createEventWithFunction($mall->id);

        AdjacencyRule::create([
            'function_a' => min($gas->id, $hospital->id),
            'function_b' => max($gas->id, $hospital->id),
            'type' => 'forbidden',
            'value' => 0,
        ]);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 1,
            'start_col' => 1,
            'end_row' => 1,
            'end_col' => 3,
            'end_function_id' => $mall->id,
        ]);

        GridCell::factory()->create(['row' => 1, 'col' => 1, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 2, 'function_id' => $hospital->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 3, 'function_id' => $mall->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 2, 'function_id' => $gas->id]);

        $response = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/generate");

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'forbidden_on_route');
    }

    public function test_route_cannot_be_generated_when_only_path_uses_forbidden_step(): void
    {
        $road = $this->createRoad();
        $gas = CityFunction::factory()->create(['name' => 'Gas Station']);
        $hospital = CityFunction::factory()->create(['name' => 'Hospital']);
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = $this->createEventWithFunction($store->id);

        AdjacencyRule::create([
            'function_a' => min($gas->id, $hospital->id),
            'function_b' => max($gas->id, $hospital->id),
            'type' => 'forbidden',
            'value' => 0,
        ]);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 1,
            'end_row' => 2,
            'end_col' => 4,
            'end_function_id' => $store->id,
        ]);

        GridCell::factory()->create(['row' => 2, 'col' => 1, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 2, 'function_id' => $gas->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => $hospital->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 4, 'function_id' => $store->id]);

        $response = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/generate");

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'forbidden_on_route');
    }

    public function test_route_can_be_generated_when_forbidden_functions_are_not_on_path(): void
    {
        $road = $this->createRoad();
        $gas = CityFunction::factory()->create(['name' => 'Gas Station']);
        $hospital = CityFunction::factory()->create(['name' => 'Hospital']);
        $police = CityFunction::factory()->create(['name' => 'Police']);
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = $this->createEventWithFunction($store->id);

        AdjacencyRule::create([
            'function_a' => min($gas->id, $hospital->id),
            'function_b' => max($gas->id, $hospital->id),
            'type' => 'forbidden',
            'value' => 0,
        ]);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 1,
            'end_row' => 2,
            'end_col' => 4,
            'end_function_id' => $store->id,
        ]);

        GridCell::factory()->create(['row' => 2, 'col' => 1, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 2, 'function_id' => $police->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => $store->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 4, 'function_id' => $store->id]);
        GridCell::factory()->create(['row' => 3, 'col' => 1, 'function_id' => $gas->id]);
        GridCell::factory()->create(['row' => 3, 'col' => 2, 'function_id' => $hospital->id]);

        $response = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/generate");

        $response->assertOk();
    }

    public function test_route_can_be_generated_through_non_road_functions(): void
    {
        $road = $this->createRoad();
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $park = CityFunction::factory()->create(['name' => 'Park']);
        $event = $this->createEventWithFunction($store->id);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 1,
            'start_col' => 3,
            'end_row' => 3,
            'end_col' => 2,
            'end_function_id' => $store->id,
        ]);

        GridCell::factory()->create(['row' => 1, 'col' => 3, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 2, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => $park->id]);
        GridCell::factory()->create(['row' => 3, 'col' => 2, 'function_id' => $store->id]);

        $response = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/generate");

        $response->assertOk();
        $response->assertJsonPath('route.path_cells.0.row', 1);
        $response->assertJsonPath('route.path_cells.0.col', 3);
        $response->assertJsonPath('route.path_cells.3.col', 2);
    }

    public function test_route_can_be_generated_when_endpoint_is_not_a_forbidden_function(): void
    {
        $road = $this->createRoad();
        $park = CityFunction::factory()->create(['name' => 'Park']);
        $factory = CityFunction::factory()->create(['name' => 'Factory']);
        $warehouse = CityFunction::factory()->create(['name' => 'Warehouse']);
        $event = $this->createEventWithFunction($park->id);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 1,
            'end_row' => 2,
            'end_col' => 4,
            'end_function_id' => $park->id,
        ]);

        AdjacencyRule::create([
            'function_a' => min($factory->id, $warehouse->id),
            'function_b' => max($factory->id, $warehouse->id),
            'type' => 'forbidden',
            'value' => 0,
        ]);

        GridCell::factory()->create(['row' => 2, 'col' => 1, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 2, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 4, 'function_id' => $park->id]);
        GridCell::factory()->create(['row' => 3, 'col' => 1, 'function_id' => $factory->id]);

        $response = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/generate");

        $response->assertOk();
    }

    public function test_city_planner_can_store_manual_path_and_clear_it(): void
    {
        $road = $this->createRoad();
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = $this->createEventWithFunction($store->id);
        $route = $this->createPlannedRoute($event, $store);

        GridCell::factory()->create(['row' => 2, 'col' => 1, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 2, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 4, 'function_id' => $store->id]);

        $path = [
            ['row' => 2, 'col' => 1],
            ['row' => 2, 'col' => 2],
            ['row' => 2, 'col' => 3],
            ['row' => 2, 'col' => 4],
        ];

        $storeResponse = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/path", [
                'path_cells' => $path,
            ]);

        $storeResponse->assertOk();
        $storeResponse->assertJsonPath('route.path_cells.3.col', 4);

        $this->assertNotNull($route->fresh()->path_cells);

        $deleteResponse = $this->actingAs($this->planner())
            ->deleteJson("/event-routes/{$event->id}/path");

        $deleteResponse->assertOk();
        $deleteResponse->assertJsonPath('route.path_cells', null);
        $this->assertNull($route->fresh()->path_cells);
    }

    public function test_route_can_be_generated_when_road_network_reaches_event(): void
    {
        $road = $this->createRoad();
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = $this->createEventWithFunction($store->id);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 1,
            'start_col' => 3,
            'end_row' => 3,
            'end_col' => 2,
            'end_function_id' => $store->id,
        ]);

        GridCell::factory()->create(['row' => 1, 'col' => 3, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 2, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 3, 'col' => 2, 'function_id' => $store->id]);

        $response = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/generate");

        $response->assertOk();
        $response->assertJsonPath('route.path_cells.0.row', 1);
        $response->assertJsonPath('route.path_cells.0.col', 3);
        $response->assertJsonPath('route.path_cells.3.col', 2);
    }

    public function test_route_can_be_generated_when_roads_connect_orthogonally(): void
    {
        $road = $this->createRoad();
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = $this->createEventWithFunction($store->id);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 1,
            'start_col' => 3,
            'end_row' => 3,
            'end_col' => 2,
            'end_function_id' => $store->id,
        ]);

        GridCell::factory()->create(['row' => 1, 'col' => 3, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 3, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 2, 'col' => 2, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 3, 'col' => 2, 'function_id' => $store->id]);

        $response = $this->actingAs($this->planner())
            ->postJson("/event-routes/{$event->id}/generate");

        $response->assertOk();
        $response->assertJsonPath('route.path_cells.3.col', 2);
    }

    public function test_route_index_includes_path_cells(): void
    {
        $road = $this->createRoad();
        $store = CityFunction::factory()->create(['name' => 'Store']);
        $event = $this->createEventWithFunction($store->id);

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 2,
            'start_col' => 1,
            'end_row' => 2,
            'end_col' => 4,
            'end_function_id' => $store->id,
            'path_cells' => [
                ['row' => 2, 'col' => 1],
                ['row' => 2, 'col' => 2],
                ['row' => 2, 'col' => 4],
            ],
        ]);

        $response = $this->actingAs($this->planner())->getJson('/event-routes');

        $response->assertOk();
        $response->assertJsonPath('routes.0.path_cells.1.col', 2);
    }
}
