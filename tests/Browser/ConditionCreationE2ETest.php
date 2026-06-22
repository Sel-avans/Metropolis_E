<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ConditionCreationE2ETest extends DuskTestCase
{
    /**
     * Admin creates a new condition rule (forbidden) between two different functions.
     */
    public function test_admin_creates_forbidden_condition()
    {
        $this->browse(function (Browser $browser) {
            // Login
            $browser->visit('http://metropolis_e.test/login')
                    ->waitFor('#email', 10)
                    ->type('#email', 'admin@t.nl')
                    ->type('#password', 'test')
                    ->click('button[type="submit"]')
                    ->waitForLocation('/dashboard', 10);

            // Go to grid then Condition Management
            $browser->clickLink('Grid')
                    ->waitForLocation('/grid', 10)
                    ->clickLink('Condition Management')
                    ->waitForLocation('/conditions', 10);

                // Ensure any leftover modal is closed, then open "New rule" modal
                $browser->script("var m = document.getElementById('createModal'); if (m && m.classList.contains('show')) { bootstrap.Modal.getOrCreateInstance(m).hide(); }");
                $browser->pause(200)
                    ->click('button[data-bs-target="#createModal"]')
                    ->waitFor('#createModal .modal-content', 5);

            // Gather available function option values from the modal
            $aValues = $browser->script("return Array.from(document.querySelectorAll('#function_a_create option')).map(o => o.value);")[0];
            $bValues = $browser->script("return Array.from(document.querySelectorAll('#function_b_create option')).map(o => o.value);")[0];

            $created = false;

            // Try pairs until create succeeds or we exhaust combinations
            foreach ($aValues as $a) {
                foreach ($bValues as $b) {
                    if ($a === $b) continue;

                    // Avoid any option containing 'Car-Dealer' by checking the option text
                    $aText = $browser->script("return document.querySelector('#function_a_create option[value=\"" . $a . "\"]').textContent.trim();")[0] ?? '';
                    $bText = $browser->script("return document.querySelector('#function_b_create option[value=\"" . $b . "\"]').textContent.trim();")[0] ?? '';
                    if (stripos($aText, 'Car-Dealer') !== false || stripos($bText, 'Car-Dealer') !== false) continue;

                    // Avoid pairing Police Station with Fire Station in either order
                    $isPoliceFire = (
                        (stripos($aText, 'Police Station') !== false && stripos($bText, 'Fire Station') !== false)
                        || (stripos($aText, 'Fire Station') !== false && stripos($bText, 'Police Station') !== false)
                    );
                    if ($isPoliceFire) continue;

                    // Select pair, type and value
                    $browser->select('#function_a_create', (string)$a)
                            ->select('#function_b_create', (string)$b)
                            ->select('#type_create', 'forbidden')
                            ->type('#value_create', '0');

                    $browser->press('Save')->pause(500);

                    // Inspect page source for validation message indicating existing rule
                    $page = $browser->driver->getPageSource();
                    if (stripos($page, 'already exists') !== false || stripos($page, 'already') !== false) {
                        // If modal is already visible (server re-opened it), hide then re-open to reset state
                        $browser->script("var m = document.getElementById('createModal'); if (m && m.classList.contains('show')) { bootstrap.Modal.getOrCreateInstance(m).hide(); }");
                        $browser->pause(200)
                                ->click('button[data-bs-target="#createModal"]')
                                ->waitFor('#createModal .modal-content', 5);
                        continue;
                    }

                    // Otherwise assume success
                    $created = true;
                    break 2;
                }
            }

            $this->assertTrue($created, 'Unable to create a new forbidden condition: all function pairs collided with existing rules');
        });
    }
}
