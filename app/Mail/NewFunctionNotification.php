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

    public $cityFunction;

    /**
     * Create a new message instance.
     */
    public function __construct(CityFunction $cityFunction)
    {
        $this->cityFunction = $cityFunction;
    }


    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New City Function Added',
        );
    }


    public function content(): Content
    {
        
        return new Content(
            htmlString: "
                <html>
                    <body>
                        <h2>New Function Available</h2>
                        <p>A new function has been added: <strong>{$this->cityFunction->name}</strong></p>
                        <p>Category: {$this->cityFunction->category}</p>
                        <p><strong>Note: The effects table requires updating for this newly added function.</strong></p>
                        <p>Regards, <br> Metropolis System</p>
                    </body>
                </html>
            ",
        );
    }
}