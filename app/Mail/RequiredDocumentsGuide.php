<?php

namespace App\Mail;

use App\Services\SharedRequiredDocumentsGuideService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RequiredDocumentsGuide extends Mailable
{
    use Queueable, SerializesModels;

    public const DEFAULT_SUBJECT = 'Guide des documents requis';

    public const DEFAULT_MESSAGE = "Bonjour,\n\n"
        ."Veuillez trouver en pièce jointe le Guide des documents requis pour préparer votre dossier.\n\n"
        ."Nous vous invitons à consulter ce document et à préparer les éléments demandés avant de soumettre votre dossier.\n\n"
        ."Cordialement,\nL’équipe Gestion des accès.";

    public function __construct(
        public readonly string $emailSubject,
        public readonly string $emailMessage,
        public readonly string $fileName,
        public readonly string $guideContents,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.required-documents-guide',
            with: ['emailMessage' => $this->emailMessage],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => $this->guideContents,
                $this->fileName,
            )->withMime(SharedRequiredDocumentsGuideService::MIME_TYPE),
        ];
    }
}
