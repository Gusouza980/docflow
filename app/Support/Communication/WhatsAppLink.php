<?php

namespace App\Support\Communication;

use App\Models\Client;
use App\Models\ClientContact;

class WhatsAppLink
{
    public function digits(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?: '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10 || strlen($digits) === 11) {
            $digits = '55'.$digits;
        }

        if (strlen($digits) < 12) {
            return null;
        }

        return $digits;
    }

    public function url(?string $phone, string $text): ?string
    {
        $digits = $this->digits($phone);

        if ($digits === null) {
            return null;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($text);
    }

    public function phoneFor(Client $client): ?string
    {
        $contacts = $client->relationLoaded('contacts')
            ? $client->contacts
            : $client->contacts()->get();

        $ordered = $contacts->sortByDesc(fn (ClientContact $contact): int => $contact->is_primary ? 1 : 0);

        foreach ($ordered as $contact) {
            foreach (['whatsapp', 'phone'] as $field) {
                if ($this->digits($contact->{$field}) !== null) {
                    return $contact->{$field};
                }
            }
        }

        return null;
    }

    public function forClient(Client $client, string $text): ?string
    {
        return $this->url($this->phoneFor($client), $text);
    }
}
