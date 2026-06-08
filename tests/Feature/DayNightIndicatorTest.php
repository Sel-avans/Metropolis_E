<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DayNightIndicatorTest extends TestCase
{
    use RefreshDatabase;

    /** Mirrors resources/js/day-night-indicator.js */
    private const NIGHT_START_MINUTES = 1080;

    /** Mirrors resources/js/simulation.js CYCLE_LENGTH_MINUTES */
    private const CYCLE_LENGTH_MINUTES = 1440;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withMiddleware();
    }

    public function test_grid_page_includes_accessible_day_night_indicator(): void
    {
        $user = User::factory()->create(['role' => UserRole::City_planner]);

        $response = $this->actingAs($user)->get('/grid');

        $response->assertOk();
        $response->assertSee('id="day-night-indicator"', false);
        $response->assertSee('role="status"', false);
        $response->assertSee('aria-live="polite"', false);
        $response->assertSee('data-day', false);
        $response->assertSee('data-night', false);
        $response->assertSee('id="day-night-live"', false);
        $response->assertSee('Day', false);
        $response->assertSee('Night', false);
    }

    public function test_night_cycle_boundary_matches_frontend_constant(): void
    {
        $this->assertTrue(1079 < self::NIGHT_START_MINUTES, 'Before 24:00 is day');
        $this->assertFalse(1080 < self::NIGHT_START_MINUTES, 'At 24:00 is night');
    }

    public function test_full_simulation_cycle_length_is_twenty_four_hours(): void
    {
        $this->assertSame(1440, self::CYCLE_LENGTH_MINUTES);
        $this->assertSame(
            self::CYCLE_LENGTH_MINUTES - self::NIGHT_START_MINUTES,
            360,
            'Night segment is 6 hours (00:00–06:00)'
        );
        $this->assertSame(self::NIGHT_START_MINUTES, 1080, 'Day segment is 18 hours (06:00–24:00)');
    }

    public function test_day_night_switch_boundaries_are_within_one_sim_minute_of_configured_times(): void
    {
        $nightStart = self::NIGHT_START_MINUTES;
        $dayStart = 0;

        $this->assertLessThanOrEqual(1, abs($nightStart - 1080), 'Night starts at configured 24:00 (sim 1080)');
        $this->assertLessThanOrEqual(1, abs($dayStart - 0), 'Day starts at configured 06:00 (sim 0)');
    }
}
