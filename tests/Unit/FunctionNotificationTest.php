<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Mail\NewFunctionNotification;
use App\Models\CityFunction;

class FunctionNotificationTest extends TestCase
{
    /**
     * Test if the Mailable correctly receives and stores the city function data.
     * * @test
     */
    public function it_sets_the_correct_city_function_data_in_the_mailable()
    {
        // 1. Arrange: Create a mock model without touching the database
        // We use 'make' instead of 'create' to keep it a Unit test
        $cityFunction = new CityFunction([
            'name' => 'Bibliotheek',
            'category' => 'Educatie'
        ]);

        // 2. Act: Create an instance of the Mailable
        $mailable = new NewFunctionNotification($cityFunction);

        // 3. Assert: Check if the property is set correctly
        $this->assertEquals('Bibliotheek', $mailable->cityFunction->name);
        $this->assertEquals('Educatie', $mailable->cityFunction->category);
    }

    /**
     * Test if the mail has the correct subject line.
     * * @test
     */
    public function test_it_has_the_correct_subject()
    {
        $cityFunction = new CityFunction(['name' => 'Park']);
        $mailable = new NewFunctionNotification($cityFunction);

        // Check if the subject is defined correctly in the envelope
        $this->assertEquals('New City Function Added', $mailable->envelope()->subject);
    }
}