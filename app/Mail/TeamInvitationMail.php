<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invitation $invitation,
        public string $acceptUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to join {$this->invitation->tenant->name} on scheduling-saas",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.team-invitation',
            with: [
                'tenantName' => $this->invitation->tenant->name,
                'inviterName' => $this->invitation->inviter->name ?? null,
                'role' => $this->invitation->role->label(),
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }
}
