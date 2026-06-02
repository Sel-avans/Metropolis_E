<?php

namespace Tests\Unit;

use App\Models\Effect;
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

        $modifiers = EventModifierService::getModifiersByCategory();

        $this->assertEquals(4.0, $modifiers['recreation']);
    }

    public function test_datetime_local_roundtrip_uses_dutch_display_and_utc_storage(): void
    {
        $stored = EventModifierService::fromDatetimeLocalInput('2026-06-01T16:38');
        $this->assertSame('2026-06-01 14:38:00', $stored);

        $input = EventModifierService::toDatetimeLocalValue('2026-06-01 14:38:00');
        $this->assertSame('2026-06-01T16:38', $input);
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

    public function test_one_off_event_activates_and_deactivates_on_exact_boundaries(): void
    {
        $start = Carbon::parse('2026-06-01 12:00:00', EventModifierService::STORAGE_TIMEZONE);
        $end = Carbon::parse('2026-06-01 13:00:00', EventModifierService::STORAGE_TIMEZONE);

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
        $this->assertFalse(EventModifierService::isActive($event));
        $this->assertEquals(0.0, EventModifierService::getModifiersByCategory()['recreation']);

        Carbon::setTestNow($start);
        $this->assertTrue(EventModifierService::isActive($event));
        $this->assertEquals(4.0, EventModifierService::getModifiersByCategory()['recreation']);

        Carbon::setTestNow($end);
        $this->assertFalse(EventModifierService::isActive($event));
        $this->assertEquals(0.0, EventModifierService::getModifiersByCategory()['recreation']);

        Carbon::setTestNow();
    }

    public function test_upcoming_event_is_tracked_but_not_active(): void
    {
        $now = EventModifierService::now();

        $upcoming = SimulationEvent::create([
            'name' => 'Soon Festival',
            'type' => 'one-off',
            'start_moment' => $now->copy()->addHour(),
            'end_moment' => $now->copy()->addHours(2),
        ]);

        $this->assertTrue(EventModifierService::isTracked($upcoming));
        $this->assertFalse(EventModifierService::isActive($upcoming));
        $this->assertCount(1, EventModifierService::getTrackedEvents());
        $this->assertCount(0, EventModifierService::getActiveEvents());
    }
}
