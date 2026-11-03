<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

/**
 * V.AI 對信箱來信的自動回覆。
 * 以同一個信件串回覆（In-Reply-To / References），並標記為自動回覆，
 * 讓對方的自動回覆系統不會再回信造成循環（RFC 3834）。
 */
class InboxAutoReply extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $replyText,
        public string $originalSubject,
        public string $originalFrom,
        public string $originalDate,
        public string $originalBody,
        public ?string $inReplyTo = null,
        public array $references = [],
    ) {}

    public function envelope(): Envelope
    {
        $subject = trim($this->originalSubject) === '' ? '（無主旨）' : trim($this->originalSubject);
        if (! preg_match('/^(re|回覆|回复)\s*[:：]/iu', $subject)) {
            $subject = 'Re: '.$subject;
        }

        return new Envelope(
            from: new Address(config('mail.from.address', 'service@vito1317.com'), 'vito1317（V.AI 助理）'),
            subject: $subject,
            using: [function (Email $message) {
                $headers = $message->getHeaders();
                if ($this->inReplyTo) {
                    $headers->addIdHeader('In-Reply-To', $this->inReplyTo);
                }
                if ($this->references) {
                    $headers->addIdHeader('References', $this->references);
                }
                $headers->addTextHeader('Auto-Submitted', 'auto-replied');
                $headers->addTextHeader('X-Auto-Response-Suppress', 'All');
                $headers->addTextHeader('X-VAI-Auto', '1');
            }],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.inbox-auto-reply',
            text: 'emails.inbox-auto-reply-text',
        );
    }
}
