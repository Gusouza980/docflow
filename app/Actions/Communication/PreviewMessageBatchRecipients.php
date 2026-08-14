<?php

namespace App\Actions\Communication;

use App\Models\Client;
use App\Models\Document;
use App\Models\MessageBatch;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\Receivable;
use App\Models\User;
use App\Support\Communication\ClientMessageDestination;
use App\Support\Communication\WhatsAppLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class PreviewMessageBatchRecipients
{
    public const MAX_RECIPIENTS = 100;

    public function __construct(
        private ClientMessageDestination $destination,
        private WhatsAppLink $whatsAppLink,
    ) {}

    /**
     * @param  list<int>  $clientIds
     * @return array{ready: list<array<string, mixed>>, skipped: list<array<string, mixed>>}
     */
    public function execute(
        Organization $organization,
        User $user,
        MessageTemplate $template,
        string $filter,
        array $clientIds = [],
    ): array {
        $clients = $this->clientsFor($organization, $filter, $clientIds)
            ->filter(fn (Client $client): bool => Gate::forUser($user)->allows('update', $client))
            ->take(self::MAX_RECIPIENTS)
            ->values();

        $ready = [];
        $skipped = [];

        foreach ($clients as $client) {
            $receivable = $filter === MessageBatch::FILTER_OVERDUE
                ? $this->oldestOverdueReceivable($client)
                : null;
            $reason = $this->destination->skipReason($client, $template->channel, $template->purpose);
            $row = $this->row($client, $template, $receivable);

            if ($reason !== null) {
                $skipped[] = [...$row, 'reason' => $reason];

                continue;
            }

            $ready[] = $row;
        }

        return [
            'ready' => $ready,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  list<int>  $clientIds
     * @return Collection<int, Client>
     */
    private function clientsFor(Organization $organization, string $filter, array $clientIds): Collection
    {
        $query = Client::query()
            ->with('contacts')
            ->where('organization_id', $organization->id)
            ->orderBy('display_name')
            ->orderBy('id');

        return match ($filter) {
            MessageBatch::FILTER_OVERDUE => $query
                ->whereHas('receivables', fn ($receivables) => $receivables
                    ->whereDate('due_at', '<', now()->toDateString())
                    ->whereIn('status', [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL]))
                ->get(),
            MessageBatch::FILTER_DOCUMENTS_EXPIRING => $query
                ->whereHas('documents', fn ($documents) => $documents
                    ->whereNotNull('expires_at')
                    ->whereNotIn('status', [
                        Document::STATUS_REJECTED,
                        Document::STATUS_EXPIRED,
                        Document::STATUS_REPLACED,
                    ])
                    ->whereBetween('expires_at', [now()->toDateString(), now()->addDays(7)->toDateString()]))
                ->get(),
            default => $query
                ->whereIn('id', $clientIds)
                ->get(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Client $client, MessageTemplate $template, ?Receivable $receivable): array
    {
        $variables = $this->destination->variablesFor($client, $receivable);
        $body = $template->renderBody($variables);
        $email = $this->destination->emailFor($client);

        return [
            'client_id' => $client->id,
            'display_name' => $client->display_name,
            'destination' => match ($template->channel) {
                MessageTemplate::CHANNEL_EMAIL => $email['email'] ?? null,
                MessageTemplate::CHANNEL_WHATSAPP => $this->whatsAppLink->phoneFor($client),
                MessageTemplate::CHANNEL_PORTAL => 'Portal do cliente',
                default => null,
            },
            'whatsapp_url' => $template->channel === MessageTemplate::CHANNEL_WHATSAPP
                ? $this->whatsAppLink->forClient($client, $body)
                : null,
            'preview' => str($body)->limit(160)->toString(),
        ];
    }

    private function oldestOverdueReceivable(Client $client): ?Receivable
    {
        return $client->receivables()
            ->whereDate('due_at', '<', now()->toDateString())
            ->whereIn('status', [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL])
            ->orderBy('due_at')
            ->orderBy('id')
            ->first();
    }
}
