<?php

namespace App\Notifications;

use App\Support\Mail\PortalMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PortalClientAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $subject,
        public string $message,
        public string $actionUrl,
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
        return PortalMailMessage::make()
            ->subject($this->subject)
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line($this->message)
            ->action('Acessar portal do cliente', $this->actionUrl)
            ->line('Se o botão não funcionar, copie e cole o endereço abaixo no navegador:')
            ->line($this->actionUrl);
    }
}
