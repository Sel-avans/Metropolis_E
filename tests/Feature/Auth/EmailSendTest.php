<?php

namespace Tests\Feature\Mail;

use Tests\TestCase;
use App\Models\CityFunction;
use App\Mail\NewFunctionNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmailSendTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_a_new_function_sends_notification_mail_with_correct_content()
    {
       
        Mail::fake();

        $cityFunction = CityFunction::factory()->create([
            'name' => 'Duurzaam Stadspark',
            'category' => 'Groenvoorziening'
        ]);

        
        Mail::to('admin@metropolis.com')->send(new NewFunctionNotification($cityFunction));

       
        Mail::assertSent(NewFunctionNotification::class, function ($mail) use ($cityFunction) {
            
            
            $hasRecipient = $mail->hasTo('admin@metropolis.com');

            $hasCorrectSubject = $mail->envelope()->subject === 'New City Function Added';

            
            $html = $mail->content()->htmlString;
            
            $hasCorrectContent = str_contains($html, 'Duurzaam Stadspark') &&
                                 str_contains($html, 'Groenvoorziening') &&
                                 str_contains($html, 'The effects table requires updating');

            return $hasRecipient && $hasCorrectSubject && $hasCorrectContent;
        });
    }
}