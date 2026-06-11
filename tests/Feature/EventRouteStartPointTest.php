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

class EventRouteStartPointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withMiddleware();
    }

    private function createRoadFunction(): CityFunction
    {
        return CityFunction::factory()->create([
            'name' => 'Road',
            'category' => 'mobility',
        ]);
    }

    private function createEvent(): SimulationEvent
    {
        return SimulationEvent::create([
            'name' => 'Summer Festival',
            'description' => 'Test event',
            'type' => 'one-off',
            'start_moment' => '2026-06-01 10:00:00',
            'end_moment' => '2026-06-01 18:00:00',
        ]);
    }

    public function test_city_planner_can_set_start_point_on_road_cell(): void
    {
        $planner = User::factory()->create(['role' => UserRole::City_planner]);
        $road = $this->createRoadFunction();
        $event = $this->createEvent();

        GridCell::factory()->create([
            'row' => 1,
            'col' => 1,
            'function_id' => $road->id,
        ]);

        $response = $this->actingAs($planner)->postJson('/event-routes/start-point', [
            'event_id' => $event->id,
            'row' => 1,
            'col' => 1,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'route' => [
                'event_id' => $event->id,
                'start_row' => 1,
                'start_col' => 1,
            ],
        ]);

        $this->assertDatabaseHas('event_routes', [
            'simulation_event_id' => $event->id,
            'start_row' => 1,
            'start_col' => 1,
        ]);
    }

    public function test_start_point_cannot_be_set_on_non_road_cell(): void
    {
        $planner = User::factory()->create(['role' => UserRole::City_planner]);
        $park = CityFunction::factory()->create(['name' => 'Park']);
        $event = $this->createEvent();

        GridCell::factory()->create([
            'row' => 2,
            'col' => 2,
            'function_id' => $park->id,
        ]);

        $response = $this->actingAs($planner)->postJson('/event-routes/start-point', [
            'event_id' => $event->id,
            'row' => 2,
            'col' => 2,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => 'invalid_start_point',
        ]);

        $this->assertDatabaseCount('event_routes', 0);
    }

    public function test_policy_maker_cannot_manage_event_routes(): void
    {
        $policyMaker = User::factory()->create(['role' => UserRole::Municipal_Policy_Maker]);
        $event = $this->createEvent();

        $response = $this->actingAs($policyMaker)->postJson('/event-routes/start-point', [
            'event_id' => $event->id,
            'row' => 1,
            'col' => 1,
        ]);

        $response->assertForbidden();
    }

    public function test_city_planner_can_remove_start_point(): void
    {
        $planner = User::factory()->create(['role' => UserRole::City_planner]);
        $event = $this->createEvent();

        EventRoute::create([
            'simulation_event_id' => $event->id,
            'start_row' => 1,
            'start_col' => 1,
        ]);

        $response = $this->actingAs($planner)->deleteJson("/event-routes/{$event->id}");

        $response->assertOk();
        $response->assertJson(['success' => true, 'deleted' => true]);
        $this->assertDatabaseCount('event_routes', 0);
    }

    public function test_grid_page_shows_route_planning_panel_for_city_planner(): void
    {
        $planner = User::factory()->create(['role' => UserRole::City_planner]);

        $response = $this->actingAs($planner)->get('/grid');

        $response->assertOk();
        $response->assertSee('Route Planning');
        $response->assertSee('Set start point');
    }

    public function test_start_point_can_be_replaced_for_same_event(): void
    {
        $planner = User::factory()->create(['role' => UserRole::City_planner]);
        $road = $this->createRoadFunction();
        $event = $this->createEvent();

        GridCell::factory()->create(['row' => 1, 'col' => 1, 'function_id' => $road->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 2, 'function_id' => $road->id]);

        $this->actingAs($planner)->postJson('/event-routes/start-point', [
            'event_id' => $event->id,
            'row' => 1,
            'col' => 1,
        ])->assertOk();

        $this->actingAs($planner)->postJson('/event-routes/start-point', [
            'event_id' => $event->id,
            'row' => 1,
            'col' => 2,
        ])->assertOk();

        $this->assertDatabaseCount('event_routes', 1);
        $this->assertDatabaseHas('event_routes', [
            'simulation_event_id' => $event->id,
            'start_row' => 1,
            'start_col' => 2,
        ]);
    }
}
