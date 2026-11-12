<?php

namespace App\Mail;

use App\Models\PriceAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * R9: tells the traveler that fares on a watched route dropped.
 */
class PriceDropMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PriceAlert $alert,
        public float $price,
        public ?float $previousPrice,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: sprintf(
            '[Voyenta] %s → %s fares dropped to $%s',
            $this->alert->origin, $this->alert->destination, number_format($this->price, 2),
        ));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.price-drop');
    }
}
