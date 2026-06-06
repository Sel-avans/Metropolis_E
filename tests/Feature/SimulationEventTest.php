<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CityFunction;
use App\Models\Effect;
use App\Models\EventEffect;
use App\Models\SimulationEvent;
use App\Models\User;
use App\Services\EventModifierService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withMiddleware();
    }

    /**
     * @return array<string, mixed>
     */
    private function eventEffectPayload(): array
    {
        $function = CityFunction::factory()->create(['category' => 'recreation']);

        return [
            'category_modifiers' => ['recreation' => 3],
            'city_functions' => [$function->id],
        ];
    }

    /**
     * Test if a City Planner can view the list, but CANNOT create events (403 Forbidden).
     */
    public function test_city_planner_can_view_but_cannot_manage_events(): void
    {
        $planner = User::factory()->create([
            'role' => UserRole::City_planner,
        ]);

        $responseView = $this->actingAs($planner)->get('/events');
        $responseView->assertStatus(200);

        $responseCreate = $this->actingAs($planner)->get('/events/create');
        $responseCreate->assertStatus(403);
    }

    /**
     * Test if a Municipal Policy Maker can successfully create a one-off event.
     */
    public function test_policy_maker_can_create_one_off_event(): void
    {
        $policyMaker = User::factory()->create([
            'role' => UserRole::Municipal_Policy_Maker,
        ]);

        $response = $this->actingAs($policyMaker)->post('/events', array_merge([
            'name' => 'Zomerfestival',
            'description' => 'Een groot evenement in het centrum.',
            'type' => 'one-off',
            'start_moment' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_moment' => now()->addDays(3)->format('Y-m-d H:i:s'),
        ], $this->eventEffectPayload()));

        $response->assertRedirect('/events');

        $this->assertDatabaseHas('simulation_events', [
            'name' => 'Zomerfestival',
            'type' => 'one-off',
        ]);
    }

    /**
     * Test if an Administrator can successfully create a recurring event.
     */
    public function test_administrator_can_create_recurring_event(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Administrator,
        ]);

        $response = $this->actingAs($admin)->post('/events', array_merge([
            'name' => 'Wekelijkse Markt',
            'type' => 'recurring',
            'recurring_schedule' => 'weekly',
            'recurring_start_date' => now()->format('Y-m-d'),
            'recurring_end_date' => now()->addMonth()->format('Y-m-d'),
            'recurring_start_time' => '09:00',
            'recurring_end_time' => '17:00',
        ], $this->eventEffectPayload()));

        $response->assertRedirect('/events');

        $this->assertDatabaseHas('simulation_events', [
            'name' => 'Wekelijkse Markt',
            'recurring_schedule' => 'weekly',
        ]);
    }

    /**
     * Test the dynamic validation: A 'one-off' event MUST include a start_moment.
     */
    public function test_validation_fails_if_one_off_event_misses_dates(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Administrator,
        ]);

        $response = $this->actingAs($admin)->post('/events', array_merge([
            'name' => 'Fout Evenement',
            'type' => 'one-off',
        ], $this->eventEffectPayload()));

        $response->assertSessionHasErrors(['start_moment']);

        $this->assertDatabaseMissing('simulation_events', [
            'name' => 'Fout Evenement',
        ]);
    }

    public function test_active_endpoint_returns_currently_active_events(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 22:30:00', 'UTC'));

        $user = User::factory()->create(['role' => UserRole::Administrator]);
        $function = CityFunction::factory()->create(['category' => 'recreation']);

        $moments = EventModifierService::normalizeEventMoments([
            'type' => 'one-off',
            'start_moment' => Carbon::parse('2026-06-03 00:21:00', 'UTC'),
            'end_moment' => Carbon::parse('2026-06-03 01:21:00', 'UTC'),
        ]);

        $event = SimulationEvent::create([
            'name' => 'test',
            'type' => 'one-off',
            'start_moment' => $moments['start_moment'],
            'end_moment' => $moments['end_moment'],
        ]);

        Effect::create([
            'function_id' => null,
            'simulation_event_id' => $event->id,
            'category' => 'recreation',
            'value' => 4,
        ]);

        $response = $this->actingAs($user)->getJson('/events/active');

        $response->assertOk();
        $response->assertJsonPath('events.0.name', 'test');
        $response->assertJsonPath('events.0.modifiers.recreation', 4);
    }

    public function test_simulation_endpoint_splits_events_by_cycle_duration(): void
    {
        $user = User::factory()->create(['role' => UserRole::Administrator]);
        $function = CityFunction::factory()->create(['category' => 'recreation']);

        $short = SimulationEvent::create([
            'name' => 'Short event',
            'type' => 'one-off',
            'start_moment' => now(),
            'end_moment' => now()->addHours(6),
        ]);

        $long = SimulationEvent::create([
            'name' => 'Long event',
            'type' => 'one-off',
            'start_moment' => '2026-06-12 08:00:00',
            'end_moment' => '2026-06-19 20:00:00',
        ]);

        foreach ([$short, $long] as $event) {
            Effect::create([
                'function_id' => null,
                'simulation_event_id' => $event->id,
                'category' => 'recreation',
                'value' => 2,
            ]);
            EventEffect::create([
                'simulation_event_id' => $event->id,
                'city_function_id' => $function->id,
                'modifier' => 0,
            ]);
        }

        $response = $this->actingAs($user)->getJson('/events/simulation');

        $response->assertOk();
        $response->assertJsonPath('events.0.name', 'Short event');
        $response->assertJsonPath('events.0.fits_in_cycle', true);
        $response->assertJsonPath('events.1.name', 'Long event');
        $response->assertJsonPath('events.1.fits_in_cycle', false);
        $response->assertJsonPath('events.0.affected_function_ids.0', $function->id);
    }
}
