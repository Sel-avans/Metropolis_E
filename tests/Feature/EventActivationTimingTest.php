<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CityFunction;
use App\Models\Effect;
use App\Models\GridCell;
use App\Models\SimulationEvent;
use App\Models\User;
use App\Services\EventModifierService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventActivationTimingTest extends TestCase
{
    use RefreshDatabase;

    public function test_qol_applies_modifiers_only_while_event_is_active(): void
    {
        $user = User::factory()->create(['role' => UserRole::City_planner]);

        $function = CityFunction::factory()->create(['category' => 'recreation']);
        Effect::factory()->create([
            'function_id' => $function->id,
            'simulation_event_id' => null,
            'category' => 'recreation',
            'value' => 2,
        ]);

        GridCell::factory()->create([
            'row' => 1,
            'col' => 1,
            'function_id' => $function->id,
        ]);

        $start = EventModifierService::now()->copy()->addHour();
        $end = $start->copy()->addHour();

        $event = SimulationEvent::create([
            'name' => 'Timed Festival',
            'type' => 'one-off',
            'start_moment' => $start,
            'end_moment' => $end,
        ]);

        Effect::create([
            'function_id' => null,
            'simulation_event_id' => $event->id,
            'category' => 'recreation',
            'value' => 4,
        ]);

        Carbon::setTestNow($start->copy()->subSecond());
        $before = $this->actingAs($user)->getJson('/qol/details')->assertOk()->json();
        $this->assertSame(2, $before['total_score']);

        Carbon::setTestNow($start);
        $during = $this->actingAs($user)->getJson('/qol/details')->assertOk()->json();
        $this->assertSame(6, $during['total_score']);

        Carbon::setTestNow($end);
        $after = $this->actingAs($user)->getJson('/qol/details')->assertOk()->json();
        $this->assertSame(2, $after['total_score']);

        Carbon::setTestNow();
    }
}
