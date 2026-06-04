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

        $response = $this->actingAs(User::factory()->create([
            'role' => UserRole::City_planner,
        ]))->get('/grid');

        $response->assertOk();
        $response->assertSee('id="library-search"', false);
        $response->assertSee('id="library-list"', false);
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
}
