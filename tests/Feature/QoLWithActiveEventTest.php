<?php

namespace Tests\Feature;

use App\Models\CityFunction;
use App\Models\Effect;
use App\Models\EventEffect;
use App\Models\GridCell;
use App\Models\SimulationEvent;
use App\Models\User;
use App\Enums\UserRole;
use App\Services\EventModifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QoLWithActiveEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_qol_details_includes_active_event_modifiers(): void
    {
        $user = User::factory()->create(['role' => UserRole::Administrator]);

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

        $event = SimulationEvent::create([
            'name' => 'Festival',
            'type' => 'one-off',
            'start_moment' => now()->subMinutes(5),
            'end_moment' => now()->addHour(),
        ]);

        Effect::create([
            'function_id' => null,
            'simulation_event_id' => $event->id,
            'category' => 'recreation',
            'value' => 4,
        ]);

        EventEffect::create([
            'simulation_event_id' => $event->id,
            'city_function_id' => $function->id,
            'modifier' => 0,
        ]);

        $this->assertTrue(EventModifierService::isActive($event->fresh()));
        $this->assertCount(1, EventModifierService::getActiveEvents());
        $this->assertSame(4.0, EventModifierService::getModifiersByCategoryForFunction($function->id)['recreation']);

        $response = $this->actingAs($user)->getJson('/qol/details');

        $response->assertOk();
        $response->assertJsonPath('categories.Recreation.total', 6);
        $response->assertJsonPath('total_score', 6);

        $items = $response->json('categories.Recreation.items');
        $this->assertTrue(
            collect($items)->contains(fn ($item) => str_contains($item['function'], 'Festival') && $item['value'] == 4)
        );

        $hover = $this->getJson('/qol/cell/1/1')->assertOk()->json();
        $this->assertSame(6, $hover['total_score']);
        $this->assertSame(2, $hover['categories']['Recreation']['total']);
        $this->assertEquals(4, $hover['event_modifiers']['recreation']);
    }

    public function test_hover_uses_per_cell_total_with_two_functions(): void
    {
        $user = User::factory()->create(['role' => UserRole::Administrator]);

        $police = CityFunction::factory()->create(['name' => 'Police', 'category' => 'safety']);
        $fire = CityFunction::factory()->create(['name' => 'Fire', 'category' => 'safety']);

        foreach (['safety' => 5, 'recreation' => 1, 'environment' => 1, 'mobility' => 2] as $cat => $val) {
            Effect::factory()->create(['function_id' => $police->id, 'category' => $cat, 'value' => $val]);
        }
        foreach (['safety' => 4, 'recreation' => 1, 'environment' => 1, 'amenities' => 2, 'mobility' => 2] as $cat => $val) {
            Effect::factory()->create(['function_id' => $fire->id, 'category' => $cat, 'value' => $val]);
        }

        GridCell::factory()->create(['row' => 1, 'col' => 1, 'function_id' => $police->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 2, 'function_id' => $fire->id]);

        $event = SimulationEvent::create([
            'name' => 'Festival',
            'type' => 'one-off',
            'start_moment' => now()->subMinutes(5),
            'end_moment' => now()->addHour(),
        ]);
        Effect::create([
            'function_id' => null,
            'simulation_event_id' => $event->id,
            'category' => 'recreation',
            'value' => 4,
        ]);

        EventEffect::create([
            'simulation_event_id' => $event->id,
            'city_function_id' => $police->id,
            'modifier' => 0,
        ]);
        EventEffect::create([
            'simulation_event_id' => $event->id,
            'city_function_id' => $fire->id,
            'modifier' => 0,
        ]);

        $global = $this->actingAs($user)->getJson('/qol/details')->assertOk()->json();
        $hoverPolice = $this->actingAs($user)->getJson('/qol/cell/1/1')->assertOk()->json();
        $hoverFire = $this->actingAs($user)->getJson('/qol/cell/1/2')->assertOk()->json();

        $this->assertSame(14, $hoverFire['total_score']);
        $this->assertSame(
            $hoverPolice['total_score'] + $hoverFire['total_score'],
            $global['total_score']
        );
    }

    public function test_qol_details_without_grid_ignores_event_modifiers(): void
    {
        $user = User::factory()->create(['role' => UserRole::Administrator]);

        $event = SimulationEvent::create([
            'name' => 'Empty Grid Festival',
            'type' => 'one-off',
            'start_moment' => now()->subMinutes(5),
            'end_moment' => now()->addHour(),
        ]);

        Effect::create([
            'function_id' => null,
            'simulation_event_id' => $event->id,
            'category' => 'amenities',
            'value' => 10,
        ]);

        $response = $this->actingAs($user)->getJson('/qol/details');

        $response->assertOk();
        $response->assertJsonPath('categories.Amenities.total', 0);
        $response->assertJsonPath('total_score', 0);
    }

    public function test_event_bonus_only_applies_to_attached_functions_on_grid(): void
    {
        $user = User::factory()->create(['role' => UserRole::Administrator]);

        $park = CityFunction::factory()->create(['name' => 'Park', 'category' => 'recreation']);
        $store = CityFunction::factory()->create(['name' => 'Store', 'category' => 'amenities']);

        Effect::factory()->create([
            'function_id' => $park->id,
            'category' => 'recreation',
            'value' => 3,
        ]);
        Effect::factory()->create([
            'function_id' => $store->id,
            'category' => 'amenities',
            'value' => 2,
        ]);

        GridCell::factory()->create(['row' => 1, 'col' => 1, 'function_id' => $park->id]);
        GridCell::factory()->create(['row' => 1, 'col' => 2, 'function_id' => $store->id]);

        $event = SimulationEvent::create([
            'name' => 'Festival',
            'type' => 'one-off',
            'start_moment' => now()->subMinutes(5),
            'end_moment' => now()->addHour(),
        ]);

        Effect::create([
            'function_id' => null,
            'simulation_event_id' => $event->id,
            'category' => 'recreation',
            'value' => 4,
        ]);

        EventEffect::create([
            'simulation_event_id' => $event->id,
            'city_function_id' => $park->id,
            'modifier' => 0,
        ]);

        $details = $this->actingAs($user)->getJson('/qol/details')->assertOk()->json();
        $this->assertSame(7, $details['categories']['Recreation']['total']);
        $this->assertSame(2, $details['categories']['Amenities']['total']);
        $this->assertSame(9, $details['total_score']);

        $parkHover = $this->actingAs($user)->getJson('/qol/cell/1/1')->assertOk()->json();
        $storeHover = $this->actingAs($user)->getJson('/qol/cell/1/2')->assertOk()->json();

        $this->assertSame(7, $parkHover['total_score']);
        $this->assertEquals(4, $parkHover['event_modifiers']['recreation']);
        $this->assertSame(2, $storeHover['total_score']);
        $this->assertEquals(0, $storeHover['event_modifiers']['recreation'] ?? 0);
    }
}
