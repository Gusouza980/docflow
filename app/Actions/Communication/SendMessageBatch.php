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

        $batch = DB::transaction(function () use ($organization, $user, $template, $filter, $preview): MessageBatch {
            $batch = MessageBatch::query()->create([
                'organization_id' => $organization->id,
                'created_by_user_id' => $user->id,
                'message_template_id' => $template->id,
                'channel' => $template->channel,
                'filter' => $filter,
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
                );
            }

            return $batch;
        });

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
