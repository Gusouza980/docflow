<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionTrialEndingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public int $daysRemaining,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->subscription->organization;

        return (new MailMessage)
            ->subject('Seu trial Docflow expira em '.$this->daysRemaining.' dia(s)')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('O período de trial da organização **'.$organization->name.'** expira em '.$this->daysRemaining.' dia(s).')
            ->line('Regularize a assinatura para continuar usando o Docflow sem interrupção.')
            ->action('Ver billing', route('organizations.billing.show', absolute: true))
            ->line('Em caso de dúvidas, entre em contato com o suporte Docflow.');
    }
}
