<?php

namespace App\Support\Communication;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientPortalAccess;
use App\Models\CommunicationConsent;
use App\Models\MessageTemplate;
use App\Models\Receivable;
use App\Support\DisplayFormat;

class ClientMessageDestination
{
    public function __construct(private WhatsAppLink $whatsAppLink) {}

    public function hasConsent(Client $client, string $channel, string $purpose): bool
    {
        return CommunicationConsent::query()
            ->whereBelongsTo($client)
            ->where('channel', $channel)
            ->whereIn('purpose', [$purpose, 'general'])
            ->where('status', CommunicationConsent::STATUS_GRANTED)
            ->exists();
    }

    /**
     * @return array{email: string, name: string}|null
     */
    public function emailFor(Client $client): ?array
    {
        $contacts = $client->relationLoaded('contacts')
            ? $client->contacts
            : $client->contacts()->get();

        $ordered = $contacts->sortByDesc(fn (ClientContact $contact): int => $contact->is_primary ? 1 : 0);

        foreach ($ordered as $contact) {
            if (filled($contact->email)) {
                return [
                    'email' => $contact->email,
                    'name' => $contact->name ?: $client->display_name,
                ];
            }
        }

        $access = ClientPortalAccess::query()
            ->where('client_id', $client->id)
            ->where('status', ClientPortalAccess::STATUS_ACTIVE)
            ->whereNotNull('email')
            ->orderByDesc('password_set_at')
            ->first();

        if ($access === null || ! filled($access->email)) {
            return null;
        }

        return [
            'email' => $access->email,
            'name' => $access->name ?: $client->display_name,
        ];
    }

    public function skipReason(Client $client, string $channel, string $purpose): ?string
    {
        if (! $this->hasConsent($client, $channel, $purpose)) {
            return 'Sem consentimento para este canal';
        }

        return match ($channel) {
            MessageTemplate::CHANNEL_EMAIL => $this->emailFor($client) === null
                ? 'Sem e-mail no contato'
                : null,
            MessageTemplate::CHANNEL_WHATSAPP => $this->whatsAppLink->phoneFor($client) === null
                ? 'Sem WhatsApp no contato'
                : null,
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    public function variablesFor(Client $client, ?Receivable $receivable = null): array
    {
        $variables = [
            'client_name' => $client->display_name,
        ];

        if ($receivable !== null) {
            $variables['amount'] = 'R$ '.number_format($receivable->amount_cents / 100, 2, ',', '.');
            $variables['due_at'] = DisplayFormat::date($receivable->due_at) ?? '';
        }

        return $variables;
    }
}
