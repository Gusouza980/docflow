<?php

namespace App\Mail;

use App\Contracts\Mail\TransactionalMailer;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class LogTransactionalMailer implements TransactionalMailer
{
    public function notify(object $notifiable, Notification $notification): void
    {
        Log::info('E-mail transacional registrado (driver log).', [
            'notifiable_type' => $notifiable::class,
            'notifiable_id' => $notifiable->getKey(),
            'notification' => $notification::class,
        ]);
    }
}
