<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TwoFactorBulkMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $secretKey,
        public array $recoveryCodes,
        public string $qrCodeUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Activation de la double authentification (2FA) - ' . config('app.name'),
            from: new Address(
                config('mail.from.address', 'hello@example.com'),
                config('mail.from.name', config('app.name'))
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.two-factor-bulk',
        );
    }

    public function attachments(): array
    {
        $content = "Codes de récupération 2FA - " . $this->user->name . "\n";
        $content .= "Conservez ces codes en lieu sûr. Chaque code ne peut être utilisé qu'une fois.\n\n";
        foreach ($this->recoveryCodes as $code) {
            $content .= $code . "\n";
        }

        return [
            Attachment::fromData(fn () => $content, 'codes-recuperation-2fa.txt')
                ->withMime('text/plain'),
        ];
    }
}
