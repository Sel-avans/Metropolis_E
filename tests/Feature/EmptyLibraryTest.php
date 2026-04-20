<?php

namespace Tests\Feature;

use Tests\TestCase;

class EmptyLibraryTest extends TestCase
{
    public function test_shows_message_when_no_functions_available()
    {
        $response = $this->view('gridView', ['functions' => []]);

        $response->assertSee('No functions available.');
    }
}
