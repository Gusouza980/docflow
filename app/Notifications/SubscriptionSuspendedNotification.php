<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionSuspendedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Organization $organization) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Assinatura Docflow suspensa por inadimplência')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('A assinatura da organização **'.$this->organization->name.'** foi suspensa por inadimplência.')
            ->line('Regularize o pagamento para restaurar o acesso ao Docflow.')
            ->action('Ver billing', route('organizations.billing.show', absolute: true))
            ->line('Entre em contato com o suporte se precisar de ajuda.');
    }
}
