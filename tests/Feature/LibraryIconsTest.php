<?php

namespace Tests\Feature;

use Tests\TestCase;

class LibraryIconsTest extends TestCase
{
    public function test_icons_are_displayed_for_each_function()
    {
        $response = $this->get('/grid');
        $response->assertSee('icons/school.png');
        $response->assertSee('icons/hospital.png');
        $response->assertSee('icons/park.png');
    }
}
