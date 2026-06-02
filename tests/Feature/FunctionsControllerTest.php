<?php
//Tests voor BES.2

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\CityFunction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FunctionsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function adminUser(): User
    {
        return User::factory()->create();
    }

    public function test_admin_can_create_function()
    {
        $admin = $this->adminUser();

        $response = $this->withMiddleware()
            ->actingAs($admin)
            ->post(route('functions.store'), [
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
        $admin = $this->adminUser();
        $function = CityFunction::factory()->create([
            'name' => 'Old Name',
            'category' => 'veiligheid'
        ]);

        $response = $this->withMiddleware()
            ->actingAs($admin)
            ->put(
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
        $admin = $this->adminUser();
        $function = CityFunction::factory()->create();

        $response = $this->withMiddleware()
            ->actingAs($admin)
            ->delete(
                route('functions.destroy', $function->id)
            );

        $response->assertRedirect(route('functions.index'));

        $this->assertDatabaseMissing('city_functions', [
            'id' => $function->id
        ]);
    }
}