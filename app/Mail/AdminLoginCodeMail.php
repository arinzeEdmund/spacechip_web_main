<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The emailed admin sign-in code (an alternative to the authenticator app). */
class AdminLoginCodeMail extends Mailable
{
    public function __construct(public readonly string $code, public readonly string $name, public readonly ?string $ip = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').' admin sign-in code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-login-code');
    }
}
