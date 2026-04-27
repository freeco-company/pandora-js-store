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
        public bool $isFinal = false, // 第二輪（14d）提醒，文案稍微急迫
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->isFinal
            ? "【婕樂纖仙女館】{$this->order->shipping_name}，最後提醒：分享您的使用心得"
            : "【婕樂纖仙女館】{$this->order->shipping_name}，您的訂單已到貨，分享使用心得吧！";
        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.review-reminder');
    }
}
