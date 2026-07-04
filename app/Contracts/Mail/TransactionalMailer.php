<?php

namespace App\Contracts\Mail;

use Illuminate\Notifications\Notification;

interface TransactionalMailer
{
    public function notify(object $notifiable, Notification $notification): void;
}
