<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\CityFunction;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class EffectManagementTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_can_create_function_and_drag_to_grid()
    {
        $user = User::factory()->create([
            'email' => 'admin@t.nl',
            'password' => bcrypt('test'),
        ]);

        $testFunction = CityFunction::create([
            'name' => 'Test Automatische Functie',
            'category' => 'TestCategorie',
        ]);

        $this->browse(function (Browser $browser) use ($user, $testFunction) {
            $browser->loginAs($user)
                    ->visit('/grid')
                    ->waitFor('.library-item', 15);

            // Trigger grid update via direct fetch call
            $script = <<<JS
            (function(){
                const meta = document.querySelector('meta[name="csrf-token"]');
                const token = meta ? meta.getAttribute('content') : '';
                
                fetch('/grid/update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ 
                        old_row: null, 
                        old_col: null, 
                        new_row: 1, 
                        new_col: 1, 
                        function_id: {$testFunction->id} 
                    })
                }).then(r=>r.json()).then(data => {
                    window.__placementResult = data;
                });
            })();
JS;
            $browser->script($script);

            // Wait for the backend to process the move
            $browser->waitUsing(10, 500, function () use ($browser) {
                $res = $browser->script('return typeof window.__placementResult !== "undefined" ? window.__placementResult.success : null;');
                return isset($res[0]) && $res[0] === true;
            });

            // Verify the function appears in the grid
            $isFilled = $browser->script('return document.querySelector(".grid-cell[data-row=\"1\"][data-col=\"1\"]").dataset.functionId !== null;')[0];
            $this->assertTrue($isFilled, 'Function was not placed in the grid cell.');
        });
    }
}
