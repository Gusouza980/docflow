<?php

namespace App\Mail;

use App\Contracts\Mail\TransactionalMailer;
use Illuminate\Notifications\Notification;

class LaravelTransactionalMailer implements TransactionalMailer
{
    public function notify(object $notifiable, Notification $notification): void
    {
        $notifiable->notify($notification);
    }
}
