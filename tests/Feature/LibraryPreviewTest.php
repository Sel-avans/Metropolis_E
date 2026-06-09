<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AdjacencyRule;
use App\Models\CityFunction;
use App\Models\Condition;
use App\Models\Effect;
use App\Models\User;
use App\Services\FunctionLibraryPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withMiddleware();
    }

    public function test_grid_page_includes_library_preview_panel_and_interactive_destinations(): void
    {
        $user = User::factory()->create(['role' => UserRole::City_planner]);
        CityFunction::factory()->create(['name' => 'Police Station', 'category' => 'safety']);

        $response = $this->actingAs($user)->get('/grid');

        $response->assertOk();
        $response->assertSee('id="library-preview-panel"', false);
        $response->assertSee('id="library-list"', false);
        $response->assertSee('Effects by category', false);
        $response->assertSee('Placement conditions', false);
        $response->assertSee('id="library-preview-effects"', false);
        $response->assertSee('id="library-preview-conditions"', false);
        $response->assertSee('library-preview-section-title', false);
        $response->assertSee('id="library-preview-close"', false);
        $response->assertSee('id="library-scroll"', false);
        $response->assertSee('id="library-search"', false);
    }

    public function test_preview_endpoint_returns_effects_grouped_by_category(): void
    {
        $user = User::factory()->create(['role' => UserRole::City_planner]);
        $function = CityFunction::factory()->create(['name' => 'Park', 'category' => 'recreation']);

        Effect::factory()->create([
            'function_id' => $function->id,
            'category' => 'recreation',
            'value' => 4,
        ]);

        Effect::factory()->create([
            'function_id' => $function->id,
            'category' => 'safety',
            'value' => -1,
        ]);

        $response = $this->actingAs($user)->getJson("/functions/{$function->id}/preview");

        $response->assertOk();
        $response->assertJsonPath('function.name', 'Park');
        $response->assertJsonPath('effects.0.category', 'safety');
        $response->assertJsonPath('effects.0.display_value', '-1');
        $response->assertJsonPath('effects.1.category', 'recreation');
        $response->assertJsonPath('effects.0.value_tone', 'negative');
        $response->assertJsonPath('effects.1.value_tone', 'positive');

        foreach (array_keys(FunctionLibraryPreviewService::EFFECT_CATEGORIES) as $category) {
            $response->assertJsonFragment(['category' => $category]);
        }
    }

    public function test_preview_endpoint_uses_placeholders_for_missing_effect_values(): void
    {
        $user = User::factory()->create(['role' => UserRole::City_planner]);
        $function = CityFunction::factory()->create();

        $response = $this->actingAs($user)->getJson("/functions/{$function->id}/preview");

        $response->assertOk();

        $effects = $response->json('effects');
        $this->assertCount(5, $effects);
        $this->assertTrue(collect($effects)->every(fn (array $effect) => $effect['display_value'] === '—' && $effect['value_tone'] === 'missing'));
    }

    public function test_preview_endpoint_lists_placement_conditions(): void
    {
        $user = User::factory()->create(['role' => UserRole::City_planner]);
        $park = CityFunction::factory()->create(['name' => 'Park']);
        $road = CityFunction::factory()->create(['name' => 'Road']);

        Condition::create([
            'function_a' => $park->id,
            'function_b' => $road->id,
            'type' => 'forbidden',
            'value' => null,
        ]);

        AdjacencyRule::create([
            'function_a' => $park->id,
            'function_b' => $road->id,
            'type' => 'forbidden',
            'value' => 0,
        ]);

        $response = $this->actingAs($user)->getJson("/functions/{$park->id}/preview");

        $response->assertOk();
        $response->assertJsonFragment([
            'partner_name' => 'Road',
            'type' => 'forbidden',
            'type_label' => 'Forbidden',
            'display_value' => 'Forbidden',
        ]);

        $this->assertCount(1, $response->json('conditions'));
    }

    public function test_preview_endpoint_returns_consistent_shape_for_all_destination_types(): void
    {
        $user = User::factory()->create(['role' => UserRole::City_planner]);

        $destinations = [
            CityFunction::factory()->create(['name' => 'Police Station', 'category' => 'safety']),
            CityFunction::factory()->create(['name' => 'Park', 'category' => 'recreation']),
            tap(CityFunction::factory()->create(['name' => 'School', 'category' => 'amenities']), function ($fn) {
                $fn->forceFill(['sensitivity' => 'sensitive'])->save();
            }),
            tap(CityFunction::factory()->create(['name' => 'Store', 'category' => 'amenities']), function ($fn) {
                $fn->forceFill(['pollution' => 'polluting'])->save();
            }),
        ];

        foreach ($destinations as $destination) {
            $response = $this->actingAs($user)->getJson("/functions/{$destination->id}/preview");

            $response->assertOk();
            $response->assertJsonStructure([
                'function' => ['id', 'name', 'category', 'image'],
                'effects' => [['category', 'label', 'value', 'display_value', 'value_tone']],
                'conditions' => [['type', 'type_label', 'display_value', 'description']],
            ]);
            $this->assertCount(5, $response->json('effects'));
            $this->assertNotEmpty($response->json('conditions'));
        }
    }

    public function test_preview_endpoint_completes_within_one_second(): void
    {
        $user = User::factory()->create(['role' => UserRole::City_planner]);
        $function = CityFunction::factory()->create();

        foreach (array_keys(FunctionLibraryPreviewService::EFFECT_CATEGORIES) as $category) {
            Effect::factory()->create([
                'function_id' => $function->id,
                'category' => $category,
                'value' => 1,
            ]);
        }

        $start = microtime(true);
        $response = $this->actingAs($user)->getJson("/functions/{$function->id}/preview");
        $duration = microtime(true) - $start;

        $response->assertOk();
        $this->assertLessThan(1.0, $duration);
    }

    public function test_preview_conditions_are_sorted_consistently(): void
    {
        $user = User::factory()->create(['role' => UserRole::City_planner]);
        $park = CityFunction::factory()->create(['name' => 'Park']);
        $road = CityFunction::factory()->create(['name' => 'Road']);
        $store = CityFunction::factory()->create(['name' => 'Store']);

        Condition::create([
            'function_a' => $park->id,
            'function_b' => $store->id,
            'type' => 'bonus',
            'value' => 2,
        ]);

        Condition::create([
            'function_a' => $park->id,
            'function_b' => $road->id,
            'type' => 'forbidden',
            'value' => null,
        ]);

        $response = $this->actingAs($user)->getJson("/functions/{$park->id}/preview");

        $types = collect($response->json('conditions'))->pluck('type')->all();
        $this->assertSame(['forbidden', 'bonus'], $types);
    }

    public function test_guest_cannot_access_preview_endpoint(): void
    {
        $function = CityFunction::factory()->create();

        $this->getJson("/functions/{$function->id}/preview")
            ->assertUnauthorized();
    }
}
