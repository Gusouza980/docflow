<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PortalResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $token,
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
        $url = route('portal.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->email,
        ], absolute: true);

        return (new MailMessage)
            ->subject('Redefinição de senha do portal')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Recebemos uma solicitação para redefinir a senha do seu acesso ao portal do cliente.')
            ->action('Redefinir senha', $url)
            ->line('Se você não solicitou a redefinição, ignore este e-mail.')
            ->line('Este link expira em 60 minutos.');
    }
}
