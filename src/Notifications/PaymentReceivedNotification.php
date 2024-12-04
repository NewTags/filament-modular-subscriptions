<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification
{
    use Queueable;

    private $payment;

    public function __construct($payment)
    {
        $this->payment = $payment;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'invoice_id' => $this->payment->invoice_id,
            'amount' => $this->payment->amount,
            'subtotal' => $this->payment->invoice->subtotal,
            'tax' => $this->payment->invoice->tax,
            'total' => $this->payment->invoice->amount,
            'currency' => $this->payment->invoice->subscription->plan->currency,
            'status' => $this->payment->status->value,
        ];
    }
} 