<?php

namespace App\Notifications;

use App\Models\SubscriptionInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionInvoiceIssuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SubscriptionInvoice $invoice) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->invoice->amount_cents / 100, 2, ',', '.');

        return (new MailMessage)
            ->subject('Nova fatura Docflow emitida')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Uma nova fatura de assinatura foi emitida no valor de R$ '.$amount.'.')
            ->line('Vencimento: '.$this->invoice->due_at?->format('d/m/Y'))
            ->action('Ver billing', route('organizations.billing.show', absolute: true))
            ->line('Entre em contato com o suporte para instruções de pagamento.');
    }
}
