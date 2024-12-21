<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceGeneratedNotification extends Notification
{
    use Queueable;

    private $invoice;

    public function __construct($invoice)
    {
        $this->invoice = $invoice;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->line('A new invoice has been generated.')
            ->action('View Invoice', url('/invoice/' . $this->invoice->id))
            ->line('Thank you for using our application!');
    }

    public function toArray($notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'subtotal' => $this->invoice->subtotal,
            'tax' => $this->invoice->tax,
            'amount' => $this->invoice->amount,
            'currency' =>  config('filament-modular-subscriptions.main_currency'),
            'due_date' => $this->invoice->due_date->format('Y-m-d')
        ];
    }
} 