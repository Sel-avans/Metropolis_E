<?php

namespace Tests\Feature;

use Tests\TestCase;

class GridSelectionTest extends TestCase
{
    public function test_grid_page_loads_successfully()
    {
        $response = $this->get('/grid');
        $response->assertStatus(200);
        $response->assertSee('City Grid');
    }
}
