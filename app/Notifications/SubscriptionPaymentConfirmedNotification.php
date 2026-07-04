<?php

namespace App\Notifications;

use App\Models\SubscriptionInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionPaymentConfirmedNotification extends Notification implements ShouldQueue
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
            ->subject('Pagamento Docflow confirmado')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Confirmamos o pagamento da fatura no valor de R$ '.$amount.'.')
            ->line('Sua assinatura está ativa. Obrigado por continuar com o Docflow!')
            ->action('Acessar o app', route('dashboard', absolute: true));
    }
}
