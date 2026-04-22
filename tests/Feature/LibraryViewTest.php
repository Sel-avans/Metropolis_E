<?php

namespace Tests\Feature;

use Tests\TestCase;

class LibraryViewTest extends TestCase
{
    public function test_library_page_loads_successfully()
    {
        $response = $this->get('/grid');
        $response->assertStatus(200);
        $response->assertSee('Function Library');
    }
}
