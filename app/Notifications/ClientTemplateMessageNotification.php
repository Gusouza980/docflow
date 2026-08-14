<?php

namespace App\Notifications;

use App\Support\Mail\PortalMailMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientTemplateMessageNotification extends Notification
{
    public function __construct(
        public string $subject,
        public string $body,
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
        $name = $notifiable->name ?? 'cliente';
        $mail = PortalMailMessage::make()
            ->subject($this->subject)
            ->greeting('Olá, '.$name.'!');

        foreach (preg_split('/\R/', $this->body) ?: [] as $line) {
            if ($line !== '') {
                $mail->line($line);
            }
        }

        return $mail;
    }
}
