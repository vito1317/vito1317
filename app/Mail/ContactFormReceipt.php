<?php

namespace App\Mail;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public $contact;

    /** V.AI 撰寫的初步回覆（生成失敗時為 null，信件照常寄出） */
    public ?string $aiReply;

    public function __construct(Contact $contact, ?string $aiReply = null)
    {
        $this->contact = $contact;
        $this->aiReply = $aiReply;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '感謝您的訊息，vito1317 已收到！',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-receipt',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}