<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\SimulationEvent;
use App\Enums\UserRole;

class SimulationEventTest extends TestCase
{
    // Ensures the database is wiped clean after every single test
    use RefreshDatabase;

    /**
     * Test if a City Planner can view the list, but CANNOT create events (403 Forbidden).
     */
    public function test_city_planner_can_view_but_cannot_manage_events(): void
    {
        // Create a temporary City Planner user
        $planner = User::factory()->create([
            'role' => UserRole::City_planner, // Ensure this matches your specific Enum!
        ]);

        // Viewing the index should be allowed (CanViewGridPage route)
        $responseView = $this->actingAs($planner)->get('/events');
        $responseView->assertStatus(200);

        // Accessing the create form should be blocked (CanManageEvents route)
        $responseCreate = $this->actingAs($planner)->get('/events/create');
        $responseCreate->assertStatus(403);
    }

    /**
     * Test if a Municipal Policy Maker can successfully create a one-off event.
     */
    public function test_policy_maker_can_create_one_off_event(): void
    {
        // Create a temporary Policy Maker user
        $policyMaker = User::factory()->create([
            'role' => UserRole::Municipal_Policy_Maker,
        ]);

        // Submit the form data for a one-off event
        $response = $this->actingAs($policyMaker)->post('/events', [
            'name' => 'Zomerfestival',
            'description' => 'Een groot evenement in het centrum.',
            'type' => 'one-off',
            'start_moment' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_moment' => now()->addDays(3)->format('Y-m-d H:i:s'),
        ]);

        // Assert that the user is redirected back to the events index upon success
        $response->assertRedirect('/events');

        // Verify that the exact record was successfully saved in the database
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
        // Create a temporary Administrator user
        $admin = User::factory()->create([
            'role' => UserRole::Administrator,
        ]);

        // Submit the form data for a recurring event
        $response = $this->actingAs($admin)->post('/events', [
            'name' => 'Wekelijkse Markt',
            'type' => 'recurring',
            'recurring_schedule' => 'weekly',
            // start_moment and end_moment are intentionally left blank here!
        ]);

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

        // Intentionally submit invalid data (a one-off event without required dates)
        $response = $this->actingAs($admin)->post('/events', [
            'name' => 'Fout Evenement',
            'type' => 'one-off',
        ]);

        // Assert that Laravel catches the error for the missing start_moment
        $response->assertSessionHasErrors(['start_moment']);
        
        // Assert that the invalid event was NOT saved to the database
        $this->assertDatabaseMissing('simulation_events', [
            'name' => 'Fout Evenement',
        ]);
    }
}