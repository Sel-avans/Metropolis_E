<?php

namespace Tests\Unit;

use App\Models\Effect;
use App\Models\EventEffect;
use App\Models\SimulationEvent;
use App\Services\EventModifierService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventModifierServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_one_off_event_applies_modifiers(): void
    {
        $now = EventModifierService::now();

        $event = SimulationEvent::create([
            'name' => 'Test Festival',
            'type' => 'one-off',
            'start_moment' => $now->copy()->subHour(),
            'end_moment' => $now->copy()->addHour(),
        ]);

        Effect::create([
            'function_id' => null,
            'simulation_event_id' => $event->id,
            'category' => 'recreation',
            'value' => 4,
        ]);

        $function = \App\Models\CityFunction::factory()->create();
        EventEffect::create([
            'simulation_event_id' => $event->id,
            'city_function_id' => $function->id,
            'modifier' => 0,
        ]);

        $modifiers = EventModifierService::getModifiersByCategoryForFunction($function->id);

        $this->assertEquals(4.0, $modifiers['recreation']);
    }

    public function test_datetime_local_roundtrip_uses_dutch_display_and_utc_storage(): void
    {
        $stored = EventModifierService::fromDatetimeLocalInput('2026-06-01T16:38');
        $this->assertSame('2026-06-01 14:38:00', $stored);

        $input = EventModifierService::toDatetimeLocalValue('2026-06-01 14:38:00');
        $this->assertSame('2026-06-01T16:38', $input);
    }

    public function test_normalize_event_moments_converts_validated_carbon_to_utc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 22:30:00', 'UTC'));

        $normalized = EventModifierService::normalizeEventMoments([
            'type' => 'one-off',
            'start_moment' => Carbon::parse('2026-06-03 00:21:00', 'UTC'),
            'end_moment' => Carbon::parse('2026-06-03 01:21:00', 'UTC'),
        ]);

        $this->assertSame('2026-06-02 22:21:00', $normalized['start_moment']);
        $this->assertSame('2026-06-02 23:21:00', $normalized['end_moment']);

        $event = SimulationEvent::create([
            'name' => 'Active Test',
            'type' => 'one-off',
            'start_moment' => $normalized['start_moment'],
            'end_moment' => $normalized['end_moment'],
        ]);

        $this->assertTrue(EventModifierService::isActive($event));
    }

    public function test_expired_event_does_not_apply_modifiers(): void
    {
        $event = SimulationEvent::create([
            'name' => 'Past Festival',
            'type' => 'one-off',
            'start_moment' => now()->subHours(3),
            'end_moment' => now()->subHour(),
        ]);

        Effect::create([
            'function_id' => null,
            'simulation_event_id' => $event->id,
            'category' => 'recreation',
            'value' => 4,
        ]);

        $modifiers = EventModifierService::getModifiersByCategory();

        $this->assertEquals(0.0, $modifiers['recreation']);
    }
}
