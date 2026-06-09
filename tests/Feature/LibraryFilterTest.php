<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CityFunction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_grid_page_includes_library_search_and_filter_controls(): void
    {
        CityFunction::factory()->create([
            'name' => 'Alpha Park',
            'category' => 'recreation',
        ]);

        $startTime = microtime(true);

        $response = $this->actingAs(User::factory()->create([
            'role' => UserRole::City_planner,
        ]))->get('/grid');

        $duration = microtime(true) - $startTime;
        $this->assertLessThan(1.0, $duration, "De pagina laadt te traag (duurde {$duration}s)");

        $response->assertOk();
        
        // HERSTELD: Aangepast naar 'library-search' om exact te matchen met de HTML in gridViewblade.php
        $response->assertSee('id="library-search"', false);
        
        $response->assertSee('class="library-item', false);
        $response->assertSee('data-function-name="Alpha Park"', false);
        $response->assertDontSee('library-function-checkbox', false);
        $response->assertDontSee('library-category-checkbox', false);
    }

    public function test_destinations_are_ordered_alphabetically_within_category(): void
    {
        CityFunction::factory()->create(['name' => 'Zebra Zone', 'category' => 'mobility']);
        CityFunction::factory()->create(['name' => 'Alpha Road', 'category' => 'mobility']);
        CityFunction::factory()->create(['name' => 'Middle Lane', 'category' => 'mobility']);

        $response = $this->actingAs(User::factory()->create([
            'role' => UserRole::City_planner,
        ]))->get('/grid');

        $content = $response->getContent();
        $alphaPos = strpos($content, 'Alpha Road');
        $middlePos = strpos($content, 'Middle Lane');
        $zebraPos = strpos($content, 'Zebra Zone');

        $this->assertNotFalse($alphaPos);
        $this->assertNotFalse($middlePos);
        $this->assertNotFalse($zebraPos);
        $this->assertLessThan($middlePos, $alphaPos);
        $this->assertLessThan($zebraPos, $middlePos);
    }

    /**
     * NIEUW: Deze test levert het keiharde bewijs voor Feedback punt 6.
     * Hij controleert specifiek het preview-endpoint op prestaties en JSON data-structuur.
     */
    public function test_preview_endpoint_returns_json_and_responds_within_one_second(): void
    {
        // Maak een functie aan om te testen
        $function = CityFunction::factory()->create([
            'name' => 'Test Preview Center',
            'category' => 'recreation'
        ]);

        // Sla de starttijd op in microseconden
        $startTime = microtime(true);

        // Roep het specifieke preview-endpoint aan dat door de JS fetch gebruikt wordt
        $response = $this->actingAs(User::factory()->create([
            'role' => UserRole::City_planner,
        ]))->getJson("/functions/{$function->id}/preview");

        // Bereken de doorlooptijd
        $duration = microtime(true) - $startTime;

        // Test faalt direct als de database/controller-response langer dan 1 seconde duurt
        $this->assertLessThan(
            1.0, 
            $duration, 
            "Het preview-endpoint is te traag voor de AC-eis! (duurde: {$duration}s, limiet is 1.0s)"
        );

        // Controleer of de HTTP status 200 OK is en of de JSON-structuur exact klopt met je controller
        $response->assertOk()
            ->assertJsonStructure([
                'name',
                'effects',
                'conditions'
            ])
            ->assertJsonPath('name', 'Test Preview Center');
    }
}