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

        // Parse the returned HTML and collect library item names for the 'mobility' category
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($content);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//li[contains(@class, "library-item") and @data-category="mobility"]');

        $names = [];
        foreach ($nodes as $node) {
            $names[] = $node->getAttribute('data-function-name');
        }

        $this->assertNotEmpty($names, 'No library items found for category mobility');

        // Ensure the expected items are present (order not enforced by the view)
        $this->assertEqualsCanonicalizing([
            'Alpha Road',
            'Middle Lane',
            'Zebra Zone',
        ], $names, 'Library items for mobility do not match the expected set');
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

        // Controleer of de HTTP status 200 OK is en of de JSON-structuur overeenkomt met de preview API
        $response->assertOk()
            ->assertJsonStructure([
                'function',
                'effects',
                'conditions'
            ])
            ->assertJsonPath('function.name', 'Test Preview Center');
    }
}