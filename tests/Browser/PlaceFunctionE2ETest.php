<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class PlaceFunctionE2ETest extends DuskTestCase
{
    /**
     * End-to-end: login, navigate to grid, place function, assert QoL updates.
     */
    public function test_admin_places_library_item_and_qol_updates()
    {
        $this->browse(function (Browser $browser) {
            // Visit login page on target domain
            $browser->visit('http://metropolis_e.test/login')
                    ->waitFor('#email', 10)
                    ->type('#email', 'admin@t.nl')
                    ->type('#password', 'test')
                    ->click('button[type="submit"]')
                    ->waitForLocation('/dashboard', 200);

            // Click Grid link in navigation
            $browser->clickLink('Grid')
                    ->waitForLocation('/grid', 200)
                    ->waitFor('.library-item', 50)
                    ->waitFor('#qol-score-value', 50);

            // Get initial QoL score (may be empty)
            $initial = $browser->script('return document.getElementById("qol-score-value").innerText;')[0] ?? '';

            // JS: POST to /grid/update using first library item id
            $script = <<<'JS'
(function(){
    const meta = document.querySelector('meta[name="csrf-token"]');
    const token = meta ? meta.getAttribute('content') : '';
    const lib = document.querySelector('.library-item');
    if(!lib){ window.__placementResult = {success:false, error:'no-library-item'}; return; }
    const functionId = lib.dataset.functionId;

    fetch('/grid/update', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ old_row: null, old_col: null, new_row: 1, new_col: 1, function_id: functionId })
    }).then(r=>r.json()).then(data=>{
        window.__placementResult = data;
    }).catch(e=>{ window.__placementResult = {success:false, error:String(e)}; });
})();
JS;

            $browser->script($script);

            // Wait for placement result success
            $browser->waitUsing(15, 500, function () use ($browser) {
                $res = $browser->script('return typeof window.__placementResult !== "undefined" ? window.__placementResult.success : null;');
                return isset($res[0]) && $res[0] === true;
            });

            // Wait for QoL score change
            $browser->waitUsing(15, 500, function () use ($browser, $initial) {
                $val = $browser->script('return document.getElementById("qol-score-value").innerText;');
                $cur = $val[0] ?? '';
                return $cur !== $initial && $cur !== '';
            });

            // Final assertion: QoL score not equal to initial
            $final = $browser->script('return document.getElementById("qol-score-value").innerText;')[0] ?? '';
            $this->assertNotEquals($initial, $final, 'QoL score did not update after placement');
        });
    }
}
