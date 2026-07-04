<?php

namespace App\Support\Mail;

use Illuminate\Notifications\Messages\MailMessage;

class PortalMailMessage
{
    public static function make(): MailMessage
    {
        return (new MailMessage)
            ->salutation('Atenciosamente, equipe '.config('app.name'));
    }
}
