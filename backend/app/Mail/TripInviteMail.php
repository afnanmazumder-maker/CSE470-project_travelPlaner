<?php

namespace App\Mail;

use App\Models\TripMember;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * R16: invites someone to a group trip with a join link.
 */
class TripInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TripMember $invite, public string $joinUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: sprintf(
            '[Voyenta] %s invited you to "%s"',
            $this->invite->inviter?->name ?? 'A traveler', $this->invite->budget->title,
        ));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.trip-invite');
    }
}
