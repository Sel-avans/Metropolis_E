<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Effect;
use App\Models\SimulationEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveSimulationEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_endpoint_excludes_expired_events(): void
    {
        $user = User::factory()->create(['role' => UserRole::City_planner]);

        $active = SimulationEvent::create([
            'name' => 'Live Festival',
            'type' => 'one-off',
            'start_moment' => now()->subHour(),
            'end_moment' => now()->addHour(),
        ]);

        $expired = SimulationEvent::create([
            'name' => 'Past Festival',
            'type' => 'one-off',
            'start_moment' => now()->subHours(3),
            'end_moment' => now()->subMinute(),
        ]);

        Effect::create([
            'function_id' => null,
            'simulation_event_id' => $active->id,
            'category' => 'recreation',
            'value' => 2,
        ]);

        Effect::create([
            'function_id' => null,
            'simulation_event_id' => $expired->id,
            'category' => 'recreation',
            'value' => 99,
        ]);

        $response = $this->actingAs($user)->getJson('/events/active');

        $response->assertOk();
        $response->assertJsonCount(1, 'events');
        $response->assertJsonPath('events.0.id', $active->id);
        $response->assertJsonPath('events.0.name', 'Live Festival');
        $response->assertJsonPath('events.0.is_active', true);
        $response->assertJsonStructure(['server_now_ms']);
    }

    public function test_active_endpoint_includes_upcoming_event_without_modifiers(): void
    {
        $user = User::factory()->create(['role' => UserRole::City_planner]);

        $upcoming = SimulationEvent::create([
            'name' => 'Future Festival',
            'type' => 'one-off',
            'start_moment' => now()->addHour(),
            'end_moment' => now()->addHours(2),
        ]);

        Effect::create([
            'function_id' => null,
            'simulation_event_id' => $upcoming->id,
            'category' => 'recreation',
            'value' => 5,
        ]);

        $response = $this->actingAs($user)->getJson('/events/active');

        $response->assertOk();
        $response->assertJsonCount(1, 'events');
        $response->assertJsonPath('events.0.id', $upcoming->id);
        $response->assertJsonPath('events.0.is_active', false);
        $response->assertJsonPath('events.0.modifiers', []);
    }
}
