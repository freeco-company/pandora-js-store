<?php

namespace App\Mail;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BackInStockMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Product $product) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "【婕樂纖仙女館】{$this->product->name} 已到貨",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.back-in-stock');
    }
}
