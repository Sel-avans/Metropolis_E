<?php
// Test voor EFF.1

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Effect;
use App\Models\CityFunction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EffectsControllerTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_effects_can_be_updated()
    {
        // Maak een city function aan
        $function = CityFunction::factory()->create();

        // Maak bestaand effect aan
        Effect::create([
            'function_id' => $function->id,
            'category' => 'veiligheid',
            'value' => 1
        ]);

        // Stuur update request
        $response = $this->postJson('/effects/update', [
            'function_id' => $function->id,
            'effects' => [
                'veiligheid' => 3
            ]
        ]);

        // Controleer response
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true
                 ]);

        // Controleer database update
        $this->assertDatabaseHas('effects', [
            'function_id' => $function->id,
            'category' => 'veiligheid',
            'value' => 3
        ]);
    }
}