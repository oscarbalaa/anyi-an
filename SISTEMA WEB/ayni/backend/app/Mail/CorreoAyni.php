<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Correo con la plantilla de AYNI (resources/views/correos) */
class CorreoAyni extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $asunto,
        public readonly string $vista,
        public readonly array $datos,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "AYNI · {$this->asunto}");
    }

    public function content(): Content
    {
        return new Content(view: "correos.{$this->vista}", with: $this->datos + ['titulo' => $this->asunto]);
    }
}
