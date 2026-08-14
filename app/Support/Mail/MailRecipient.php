<?php

namespace App\Support\Mail;

use Illuminate\Notifications\Notifiable;

class MailRecipient
{
    use Notifiable;

    public function __construct(
        public string $email,
        public string $name,
    ) {}

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    public function getKey(): string
    {
        return $this->email;
    }
}
