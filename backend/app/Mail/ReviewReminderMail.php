<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public array $productNames,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "【婕樂纖仙女館】{$this->order->shipping_name}，您的訂單已到貨，分享使用心得吧！",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.review-reminder');
    }
}
