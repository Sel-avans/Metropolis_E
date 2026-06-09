<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;


class DayNightCycleFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCityPlanner(): static
    {
        $user = User::factory()->create(['role' => UserRole::City_planner]);
        return $this->actingAs($user);
    }

    public function test_full_cycle_toggle_starts_in_disabled_state(): void
    {
        $response = $this->actingAsCityPlanner()->get('/grid');

        $response->assertOk();
        $response->assertSee('data-active="false"', false);
        $response->assertSee('aria-pressed="false"', false);
    }

    public function test_day_and_night_duration_inputs_are_rendered(): void
    {
        $response = $this->actingAsCityPlanner()->get('/grid');

        $response->assertOk();
        $response->assertSee('id="day-hours-input"', false);
        $response->assertSee('id="night-hours-input"', false);
    }

    public function test_day_input_defaults_to_18_and_night_input_defaults_to_6(): void
    {
        $response = $this->actingAsCityPlanner()->get('/grid');

        $response->assertSee('id="day-hours-input"', false);
        $response->assertSeeInOrder([
            'id="day-hours-input"',
            'value="18"',
        ], false);
        $response->assertSeeInOrder([
            'id="night-hours-input"',
            'value="6"',
        ], false);
    }

    public function test_duration_validation_message_is_hidden_on_page_load(): void
    {
        $response = $this->actingAsCityPlanner()->get('/grid');

        $response->assertSee('id="duration-validation-msg"', false);
        // Moet hidden zijn; JS toont het pas bij ongeldige invoer
        $response->assertSee('class="hidden', false);
    }

    public function test_day_input_has_correct_min_max_constraints(): void
    {
        $response = $this->actingAsCityPlanner()->get('/grid');

        $response->assertSee('min="1"', false);
        $response->assertSee('max="23"', false);
    }


    public function test_timeline_range_input_is_rendered(): void
    {
        $response = $this->actingAsCityPlanner()->get('/grid');

        $response->assertSee('id="simulation-timeline"', false);
        $response->assertSee('type="range"', false);
    }

    public function test_timeline_slider_initial_value_and_aria_attributes_are_zero(): void
    {
        $response = $this->actingAsCityPlanner()->get('/grid');

        $response->assertSee('aria-valuenow="0"', false);
        $response->assertSee('aria-valuetext="06:00"', false);
    }

    public function test_simulation_time_display_shows_start_time(): void
    {
        $response = $this->actingAsCityPlanner()->get('/grid');

        $response->assertSee('id="simulation-time-display"', false);
        $response->assertSee('06:00', false);
    }

    public function test_qol_score_value_element_is_present(): void
    {
        $response = $this->actingAsCityPlanner()->get('/grid');

        $response->assertSee('id="qol-score-value"', false);
    }

    public function test_qol_score_container_has_aria_live_polite(): void
    {
        $response = $this->actingAsCityPlanner()->get('/grid');

        $response->assertSee('aria-live="polite"', false);
        $response->assertSee('aria-atomic="true"', false);
    }

    public function test_qol_breakdown_panel_is_rendered(): void
    {
        $response = $this->actingAsCityPlanner()->get('/grid');

        $response->assertSee('id="breakdown-qol-score"', false);
    }
}