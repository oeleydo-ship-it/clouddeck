<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent straight from the settings page to prove the saved SMTP credentials work. Delivered
 * synchronously and never queued: an operator pressing "Send test" needs the failure in
 * front of them, not swallowed into a failed job they will not read.
 */
class MailSettingsTestMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $platformName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->platformName.' test message');
    }

    public function content(): Content
    {
        return new Content(text: 'emails.mail-settings-test');
    }
}
