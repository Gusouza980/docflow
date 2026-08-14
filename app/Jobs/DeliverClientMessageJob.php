<?php

namespace App\Jobs;

use App\Actions\Communication\DeliverOutboundClientMessage;
use App\Models\ClientMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeliverClientMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [1, 5, 10];

    public function __construct(public int $clientMessageId) {}

    public function handle(DeliverOutboundClientMessage $deliverOutboundClientMessage): void
    {
        $message = ClientMessage::query()->find($this->clientMessageId);

        if ($message === null) {
            return;
        }

        $deliverOutboundClientMessage->process($message);
    }

    public function failed(?Throwable $exception): void
    {
        $message = ClientMessage::query()->find($this->clientMessageId);

        if ($message === null || $message->status !== ClientMessage::STATUS_QUEUED) {
            return;
        }

        $message->update([
            'status' => ClientMessage::STATUS_FAILED,
            'failure_reason' => $exception?->getMessage() ?: 'Falha ao enviar a mensagem.',
        ]);

        Log::warning('client_message.delivery_failed', [
            'client_message_id' => $this->clientMessageId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
