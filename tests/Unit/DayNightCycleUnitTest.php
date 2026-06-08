<?php

namespace Tests\Unit;

use Tests\TestCase;

class DayNightCycleUnitTest extends TestCase
{
    private function isValidCycle(int $dayHours, int $nightHours): bool
    {
        return $dayHours >= 1
            && $nightHours >= 1
            && ($dayHours + $nightHours) === 24;
    }

    /**
     * @param bool $fullCycle false = day-only modus (altijd 'day')
     */
    private function getPhase(
        int  $elapsedMinutes,
        int  $dayHours,
        int  $nightHours,
        bool $fullCycle = true,
    ): string {
        if (!$fullCycle) {
            return 'day';
        }

        $cycleMinutes  = ($dayHours + $nightHours) * 60;
        $dayMinutes    = $dayHours * 60;
        $posInCycle    = $elapsedMinutes % $cycleMinutes;

        return $posInCycle < $dayMinutes ? 'day' : 'night';
    }

    public function test_valid_cycle_durations_pass_validation(): void
    {
        $this->assertTrue($this->isValidCycle(18, 6));
        $this->assertTrue($this->isValidCycle(12, 12));
        $this->assertTrue($this->isValidCycle(1, 23));
        $this->assertTrue($this->isValidCycle(23, 1));
    }

    public function test_durations_not_totalling_24_fail_validation(): void
    {
        $this->assertFalse($this->isValidCycle(10, 10));
        $this->assertFalse($this->isValidCycle(20, 6));
        $this->assertFalse($this->isValidCycle(13, 12));
    }

    public function test_zero_hours_for_either_phase_fails_validation(): void
    {
        $this->assertFalse($this->isValidCycle(0, 24));
        $this->assertFalse($this->isValidCycle(24, 0));
    }

    public function test_negative_hours_fail_validation(): void
    {
        $this->assertFalse($this->isValidCycle(-1, 25));
        $this->assertFalse($this->isValidCycle(25, -1));
    }

    public function test_elapsed_zero_is_day(): void
    {
        $this->assertSame('day', $this->getPhase(0, 18, 6));
    }

    public function test_last_minute_of_day_phase_is_still_day(): void
    {
        // 18 uur = 1080 min; minuut 1079 is de laatste dagminuut
        $this->assertSame('day', $this->getPhase(1079, 18, 6));
    }

    public function test_first_minute_of_night_phase_is_night(): void
    {
        // Minuut 1080 = start nacht
        $this->assertSame('night', $this->getPhase(1080, 18, 6));
    }

    public function test_last_minute_of_night_phase_is_night(): void
    {
        $this->assertSame('night', $this->getPhase(1439, 18, 6));
    }

    public function test_phase_wraps_back_to_day_at_start_of_new_cycle(): void
    {

        $this->assertSame('day', $this->getPhase(1440, 18, 6));
    }

    public function test_phase_wraps_correctly_multiple_cycles_in(): void
    {

        $this->assertSame('day',   $this->getPhase(3 * 1440 + 500,  18, 6));
        $this->assertSame('night', $this->getPhase(3 * 1440 + 1200, 18, 6));
    }

    public function test_equal_day_night_split_switches_at_midpoint(): void
    {
        $this->assertSame('day',   $this->getPhase(0,   12, 12));
        $this->assertSame('day',   $this->getPhase(719, 12, 12));
        $this->assertSame('night', $this->getPhase(720, 12, 12));
        $this->assertSame('night', $this->getPhase(1439, 12, 12));
    }

    public function test_short_day_long_night_switches_early(): void
    {
        // dag=1u=60min, nacht=23u=1380min
        $this->assertSame('day',   $this->getPhase(59,  1, 23));
        $this->assertSame('night', $this->getPhase(60,  1, 23));
        $this->assertSame('night', $this->getPhase(800, 1, 23));
    }

    public function test_default_night_start_is_1080_minutes(): void
    {
        $dayMinutes = 18 * 60;
        $this->assertSame(1080, $dayMinutes);
        $this->assertSame('night', $this->getPhase(1080, 18, 6));
    }

    public function test_default_full_cycle_is_1440_minutes(): void
    {
        $cycleMinutes = (18 + 6) * 60;
        $this->assertSame(1440, $cycleMinutes);
    }

    public function test_day_only_mode_returns_day_at_start(): void
    {
        $this->assertSame('day', $this->getPhase(0, 18, 6, fullCycle: false));
    }

    public function test_day_only_mode_returns_day_past_normal_night_boundary(): void
    {
        $this->assertSame('day', $this->getPhase(1080, 18, 6, fullCycle: false));
        $this->assertSame('day', $this->getPhase(1439, 18, 6, fullCycle: false));
    }

    public function test_day_only_mode_returns_day_multiple_cycles_in(): void
    {
        $this->assertSame('day', $this->getPhase(5000, 18, 6, fullCycle: false));
    }
}