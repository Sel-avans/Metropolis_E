<?php

namespace Tests\Browser;

use App\Models\User; // Zorg dat dit pad klopt
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class EffectManagementTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_drag_and_drop_updates_grid_cell()
    {
        // 1. Zorg dat er een user in de database staat voordat we beginnen
        $user = User::factory()->create([
            'email' => 'admin@t.nl',
            'password' => bcrypt('test'),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            // 2. Login direct zonder het formulier te doorlopen
            $browser->loginAs($user)
                    ->visit('/grid')
                    ->waitFor('.library-item', 200);

            // 3. Drag en Drop
            $browser->drag('.library-item', '.grid-cell[data-row="1"][data-col="1"]');

            $browser->pause(1000); 

            // 4. Assertie
            $browser->waitUsing(10, 500, function () use ($browser) {
                $script = 'return document.querySelector(".grid-cell[data-row=\"1\"][data-col=\"1\"]").dataset.functionId;';
                $functionId = $browser->script($script)[0];
                return !empty($functionId);
            });

            $finalId = $browser->script('return document.querySelector(".grid-cell[data-row=\"1\"][data-col=\"1\"]").dataset.functionId;')[0];
            $this->assertNotEmpty($finalId, 'Function failed to attach to grid cell.');
        });
    }
}