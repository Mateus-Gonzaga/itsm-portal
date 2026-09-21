<?php

namespace App\Mail;

use App\Data\TicketData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly TicketData $ticket,
        public readonly string $ticketUrl,
        public readonly bool $isStaffNotification = false,
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = $this->isStaffNotification
            ? "[Novo Chamado #{$this->ticket->id}] {$this->ticket->title} - {$this->ticket->entity}"
            : "[Chamado #{$this->ticket->id}] Confirmação de Abertura: {$this->ticket->title}";

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-created',
        );
    }
}
