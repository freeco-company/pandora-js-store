<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AbandonedCartMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "【婕樂纖仙女館】還差一步！您的訂單 {$this->order->order_number} 尚未完成",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.abandoned-cart');
    }
}
