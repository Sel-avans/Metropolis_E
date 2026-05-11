<?php

namespace App\Mail;

use App\Models\CityFunction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewFunctionNotification extends Mailable
{
    use Queueable, SerializesModels;

    // This property makes the model data available in the email template
    public $cityFunction;

    /**
     * Create a new message instance.
     */
    public function __construct(\App\Models\CityFunction $cityFunction)
    {
        // Assign the passed model to the public property
        $this->cityFunction = $cityFunction;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New City Function Added',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.new_function',
        );
    }
}
```php id="66fmbg"
public function content(): Content
{
    return new Content(
        html: '
            <html>
                <body>
                    <h2>New Function Available</h2>

                    <p>Hello Effects Expert,</p>

                    <p>A new function has been added to the system.</p>

                    <h3>Function Details</h3>

                    <p>
                        <strong>Name:</strong> ' . e($this->cityFunction->name) . '
                    </p>

                    <p>
                        <strong>Function:</strong> ' . e($this->cityFunction->function) . '
                    </p>

                    <p>
                        The effects table requires updating for this newly added function.
                    </p>

                    <p>
                        This notification was sent automatically immediately after the function was created.
                    </p>

                    <p>
                        Regards,<br>
                        System Notification Service
                    </p>
                </body>
            </html>
        ',
    );
}

/*
|--------------------------------------------------------------------------
| Example usage after creating a new function
|--------------------------------------------------------------------------
*/

// Prevent duplicate notifications
if (!$cityFunction->notification_sent) {

    // Send email immediately
    Mail::to('effects.expert@example.com')
        ->send(new NewFunctionNotification($cityFunction));

    // Mark notification as sent
    $cityFunction->update([
        'notification_sent' => true,
    ]);
}
```
