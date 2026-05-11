<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\CityFunction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FunctionsControllerTest extends TestCase
{
    use RefreshDatabase;

    //Tests voor BES.2
    public function test_admin_can_create_function()
    {
        $response = $this->post(route('functions.store'), [
            'name' => 'School',
            'category' => 'voorzieningen',
            'icon' => null
        ]);

        $response->assertRedirect(route('functions.index'));

        $this->assertDatabaseHas('city_functions', [
            'name' => 'School',
            'category' => 'voorzieningen'
        ]);
    }

    public function test_admin_can_edit_function()
    {
        $function = CityFunction::factory()->create([
            'name' => 'Old Name',
            'category' => 'veiligheid'
        ]);

        $response = $this->put(
            route('functions.update', $function->id),
            [
                'name' => 'New Name',
                'category' => 'mobiliteit',
                'icon' => null
            ]
        );

        $response->assertRedirect(route('functions.index'));

        $this->assertDatabaseHas('city_functions', [
            'id' => $function->id,
            'name' => 'New Name',
            'category' => 'mobiliteit'
        ]);
    }

    public function test_admin_can_delete_function()
    {
        $function = CityFunction::factory()->create();

        $response = $this->delete(
            route('functions.destroy', $function->id)
        );

        $response->assertRedirect(route('functions.index'));

        $this->assertDatabaseMissing('city_functions', [
            'id' => $function->id
        ]);
    }
}