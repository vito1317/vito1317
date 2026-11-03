<?php
namespace App\Mail;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewContactMessage extends Mailable
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
            from: env('MAIL_FROM_ADDRESS', 'service@vito1317.com'),
            subject: str_starts_with((string) $this->contact->message, '【V.AI 對話轉交】')
                ? '【V.AI 轉交】'.$this->contact->name.' 想跟你合作'
                : '個人網站收到新的聯絡訊息！',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-form',
        );
    }
}