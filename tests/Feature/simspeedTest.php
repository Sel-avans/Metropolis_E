<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\activeEvent;   
use App\Services\simspeed;     
use Illuminate\Support\Carbon;

class simspeedTest extends TestCase
{
    private simspeed $eventManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventManager = new simspeed(); 
    }

    public function test_it_calculates_effective_duration_correctly()
    {
        $this->assertEquals(50.0, $this->eventManager->calculateEffectiveDuration(100, 2.0));
        $this->assertEquals(25.0, $this->eventManager->calculateEffectiveDuration(100, 4.0));
        $this->assertEquals(100.0, $this->eventManager->calculateEffectiveDuration(100, 0.0));
    }

    public function test_it_updates_speed_change_correctly()
    {
        $knownDate = Carbon::create(2026, 5, 29, 12, 0, 0);
        Carbon::setTestNow($knownDate);

        $activeEvent = new activeEvent([
            'remaining_base_duration' => 100.0,
            'last_updated_at' => $knownDate->copy()->subSeconds(10)->toDateTimeString(),
        ]);
        
        $result = $this->eventManager->updateSpeedChange($activeEvent, 2.0, 4.0);

        $this->assertEquals(80.0, $result['remaining_base_duration']);
        $this->assertEquals(20, $result['real_seconds_remaining']);
        $this->assertEquals($knownDate->toDateTimeString(), $result['last_updated_at']);

        Carbon::setTestNow();
    }

    public function test_it_checks_if_event_is_finished_within_tolerance()
    {
        $knownDate = Carbon::create(2026, 5, 29, 12, 0, 0);
        Carbon::setTestNow($knownDate);

        $finishedEvent = new activeEvent([
            'remaining_base_duration' => 20.0,
            'last_updated_at' => $knownDate->copy()->subSeconds(10)->toDateTimeString(),
        ]);

        $this->assertTrue($this->eventManager->isEventFinished($finishedEvent, 2.0));

        $activeEvent = new activeEvent([
            'remaining_base_duration' => 20.0,
            'last_updated_at' => $knownDate->copy()->subSeconds(5)->toDateTimeString(),
        ]);

        $this->assertFalse($this->eventManager->isEventFinished($activeEvent, 2.0));

        Carbon::setTestNow();
    }
}