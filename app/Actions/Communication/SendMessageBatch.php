<?php

namespace App\Actions\Communication;

use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\MessageBatch;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\Receivable;
use App\Models\User;
use App\Support\Communication\ClientMessageDestination;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class SendMessageBatch
{
    public function __construct(
        private PreviewMessageBatchRecipients $previewMessageBatchRecipients,
        private DeliverOutboundClientMessage $deliverOutboundClientMessage,
        private ClientMessageDestination $destination,
    ) {}

    /**
     * @param  list<int>  $clientIds
     */
    public function execute(
        Organization $organization,
        User $user,
        MessageTemplate $template,
        string $filter,
        array $clientIds = [],
    ): MessageBatch {
        $preview = $this->previewMessageBatchRecipients->execute(
            $organization,
            $user,
            $template,
            $filter,
            $clientIds,
        );

        $readyIds = collect($preview['ready'])->pluck('client_id')->map(fn ($id): int => (int) $id)->sort()->values();
        $fingerprint = hash('sha256', implode('|', [
            $template->id,
            $filter,
            now()->toDateString(),
            $readyIds->implode(','),
        ]));

        $existing = MessageBatch::query()
            ->where('organization_id', $organization->id)
            ->where('fingerprint', $fingerprint)
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            $batch = $this->createBatch($organization, $user, $template, $filter, $preview, $fingerprint);
        } catch (UniqueConstraintViolationException) {
            return MessageBatch::query()
                ->where('organization_id', $organization->id)
                ->where('fingerprint', $fingerprint)
                ->firstOrFail();
        }

        if (config('queue.default') === 'sync') {
            foreach ($batch->messages()->where('status', ClientMessage::STATUS_QUEUED)->get() as $message) {
                try {
                    $this->deliverOutboundClientMessage->process($message);
                } catch (\Throwable $exception) {
                    $message->update([
                        'status' => ClientMessage::STATUS_FAILED,
                        'failure_reason' => $exception->getMessage(),
                    ]);
                }
            }
        }

        return $batch->fresh() ?? $batch;
    }

    /**
     * @param  array{ready: list<array<string, mixed>>, skipped: list<mixed>}  $preview
     */
    private function createBatch(
        Organization $organization,
        User $user,
        MessageTemplate $template,
        string $filter,
        array $preview,
        string $fingerprint,
    ): MessageBatch {
        return DB::transaction(function () use ($organization, $user, $template, $filter, $preview, $fingerprint): MessageBatch {
            $batch = MessageBatch::query()->create([
                'organization_id' => $organization->id,
                'created_by_user_id' => $user->id,
                'message_template_id' => $template->id,
                'channel' => $template->channel,
                'filter' => $filter,
                'fingerprint' => $fingerprint,
                'skipped_count' => count($preview['skipped']),
            ]);

            foreach ($preview['ready'] as $row) {
                $client = Client::query()
                    ->where('organization_id', $organization->id)
                    ->findOrFail($row['client_id']);
                $receivable = $filter === MessageBatch::FILTER_OVERDUE
                    ? $this->oldestOverdueReceivable($client)
                    : null;

                $this->deliverOutboundClientMessage->queue(
                    client: $client,
                    channel: $template->channel,
                    body: '',
                    subject: $template->subject,
                    template: $template,
                    sentBy: $user,
                    batch: $batch,
                    variables: $this->destination->variablesFor($client, $receivable),
                    deliverNow: false,
                    receivableId: $receivable?->id,
                );
            }

            return $batch;
        });
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
