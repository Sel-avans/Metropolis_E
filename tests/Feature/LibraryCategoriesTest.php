<?php

namespace Tests\Feature;

use Tests\TestCase;

class LibraryCategoriesTest extends TestCase
{
    public function test_categories_are_displayed_in_library()
    {
        $response = $this->get('/grid');
        $response->assertSee('School');
        $response->assertSee('Hospital');
        $response->assertSee('Park');
    }
}
