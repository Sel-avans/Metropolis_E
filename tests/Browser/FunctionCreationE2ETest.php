<?php
// Test voor: function creation, function management
namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class FunctionCreationE2ETest extends DuskTestCase
{
    /**
     * End-to-end: login, navigate to function management, create a new function, verify it appears in functions table.
     */
    public function test_admin_creates_new_function()
    {
        $this->browse(function (Browser $browser) {
            // Visit login page on target domain
            $browser->visit('http://metropolis_e.test/login')
                    ->waitFor('#email', 200)
                    ->type('#email', 'admin@t.nl')
                    ->type('#password', 'test')
                    ->click('button[type="submit"]')
                    ->waitForLocation('/dashboard', 200);

            // Navigate to grid page
            $browser->clickLink('Grid')
                    ->waitForLocation('/grid', 200);

            // Click function management link
            $browser->clickLink('Functions')
                    ->waitForLocation('/functions', 200);

            // Click "new function" link by href
            $browser->visit('http://metropolis_e.test/functions/create')
                    ->waitFor('input[name="name"], #name', 200);

                        // Use a unique name to avoid validation collisions in the DB
                        $uniqueName = 'Car-Dealer-' . time();

                        // Fill in the function form
                        $browser->type('input[name="name"], #name', $uniqueName);

                        // The category field is a custom readonly input (#category-input). Set it via JS and trigger events.
                        $browser->script(
                                "const el = document.querySelector('input[name=\"category\"], #category-input'); if(el){ el.value = 'Mobility'; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); }"
                        );
                        $browser->pause(200);

                    // Ensure category is set and click the submit button via JS (scroll + click)
                    $browser->script(
                        "const input = document.querySelector('input[name=\"category\"], #category-input'); if(input) { input.value='Mobility'; input.dispatchEvent(new Event('input',{bubbles:true})); input.dispatchEvent(new Event('change',{bubbles:true})); }");
                    $browser->pause(200);

                    // Use Dusk helper to press the Save button (visible text) and allow navigation
                    $browser->press('Save');
                    $browser->pause(500);

                    // Diagnostic: capture current path and body so we can see where we ended up
                    $currentUrl = $browser->driver->getCurrentURL();
                    $currentPath = parse_url($currentUrl, PHP_URL_PATH) ?: '/';
                    try {
                        $bodyText = $browser->driver->getPageSource();
                    } catch (\Exception $e) {
                        $bodyText = '<<unable to get page source: ' . $e->getMessage() . '>>';
                    }

                    // If we didn't end up on /functions, fail with diagnostic info
                    $this->assertEquals('/functions', $currentPath, "After submit ended at {$currentPath}. Body:\n" . $bodyText);

                    // Verify the function appears in the table
                    $browser->waitUsing(10, 500, function () use ($browser) {
                        return $browser->script('return document.body.innerText.includes("Car-Dealer");')[0] === true;
                    });

                    $this->assertTrue(
                        str_contains($bodyText, 'Car-Dealer'),
                        'Function "Car-Dealer" not found in functions table after creation'
                    );
        });
    }
}
