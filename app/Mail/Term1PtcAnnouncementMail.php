<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class Term1PtcAnnouncementMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $logoPath;

    public function __construct(
        public ?string $recipientName = null,
        public ?string $studentName = null,
        public ?string $gradeLevel = null,
        public ?string $dispatchRef = null,
        public ?string $fromAddress = null,
        public ?string $fromName = null,
        public ?string $attachmentPath = null,
    ) {
        $this->logoPath = public_path('images/AMIS_Logo.png');

        // Explicitly purge any threading headers from the Symfony MIME message
        $this->withSymfonyMessage(function (\Symfony\Component\Mime\Email $message) {
            $headers = $message->getHeaders();
            if ($headers->has('In-Reply-To')) {
                $headers->remove('In-Reply-To');
            }
            if ($headers->has('References')) {
                $headers->remove('References');
            }
            if ($headers->has('Thread-Topic')) {
                $headers->remove('Thread-Topic');
            }
            if ($headers->has('Thread-Index')) {
                $headers->remove('Thread-Index');
            }
        });
    }

    /**
     * Build the unique recipient-specific subject line to prevent Gmail conversation threading.
     * Format: 📢 AMIS Announcement: Term 1 Special Awards Recognition & PTC (SY 2026–2027) – [Family/Student Name]
     */
    public function resolveSubject(): string
    {
        $name = trim((string) ($this->recipientName ?: $this->studentName));
        if (empty($name)) {
            $name = 'VALUED FAMILY';
        } else {
            $name = mb_strtoupper($name);
        }

        $refSuffix = !empty($this->dispatchRef) ? " [{$this->dispatchRef}]" : '';

        return "📢 AMIS Announcement: Term 1 Special Awards Recognition & PTC (SY 2026–2027) – {$name}{$refSuffix}";
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $fromName    = $this->fromName ?: env('ANNOUNCEMENT_MAIL_FROM_NAME', 'AMIS Administration');
        $fromAddress = $this->fromAddress ?: env('ANNOUNCEMENT_MAIL_FROM_ADDRESS', config('mail.from.address', 'amisonlinesupport@gmail.com'));

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            replyTo: [new Address('amisonlinesupport@gmail.com', 'AMIS Support Staff')],
            subject: $this->resolveSubject(),
        );
    }

    /**
     * Configure unique message headers to strictly prevent Gmail conversation grouping.
     */
    public function headers(): Headers
    {
        $uniqueToken = bin2hex(random_bytes(16)) . '.' . microtime(true);
        $domain = parse_url(config('app.url', 'https://amis.edu.ph'), PHP_URL_HOST) ?: 'amis.edu.ph';

        return new Headers(
            messageId: "ptc.announcement.{$uniqueToken}@{$domain}",
            references: [],
            text: [
                'X-Entity-Ref-ID'         => (string) Str::uuid(),
                'X-AMIS-Announcement-ID' => (string) Str::uuid(),
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.term1_ptc_announcement',
            with: [
                'logoPath'      => $this->logoPath,
                'recipientName' => $this->recipientName,
                'studentName'   => $this->studentName,
                'gradeLevel'    => $this->gradeLevel,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        $attachments = [];

        if (!empty($this->attachmentPath) && file_exists($this->attachmentPath)) {
            $attachments[] = Attachment::fromPath($this->attachmentPath)
                ->as('AMIS_Term1_Awards_PTC_Memorandum.pdf')
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
