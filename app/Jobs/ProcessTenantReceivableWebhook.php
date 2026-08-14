<?php

namespace App\Jobs;

use App\Actions\Finance\MarkReceivablePaidFromGateway;
use App\Models\ReceivableCharge;
use App\Models\TenantReceivableWebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProcessTenantReceivableWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $organizationId,
        public string $eventId,
        public array $payload,
    ) {}

    public function handle(MarkReceivablePaidFromGateway $markReceivablePaidFromGateway): void
    {
        $event = TenantReceivableWebhookEvent::query()->firstOrCreate(
            [
                'organization_id' => $this->organizationId,
                'event_id' => $this->eventId,
            ],
            ['payload' => $this->payload],
        );

        if ($event->isProcessed()) {
            return;
        }

        DB::transaction(function () use ($event, $markReceivablePaidFromGateway): void {
            $handled = $this->process($markReceivablePaidFromGateway);

            if ($handled === false) {
                throw new RuntimeException('Tenant receivable webhook could not be processed.');
            }

            $event->update(['processed_at' => now()]);
        });
    }

    private function process(MarkReceivablePaidFromGateway $markReceivablePaidFromGateway): bool
    {
        $eventName = (string) ($this->payload['event'] ?? '');

        if (! in_array($eventName, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true)) {
            return true;
        }

        /** @var array<string, mixed> $payment */
        $payment = is_array($this->payload['payment'] ?? null) ? $this->payload['payment'] : [];
        $paymentId = (string) ($payment['id'] ?? '');

        if ($paymentId === '') {
            return true;
        }

        $charge = ReceivableCharge::query()
            ->where('organization_id', $this->organizationId)
            ->where('provider_payment_id', $paymentId)
            ->first();

        if ($charge === null) {
            return true;
        }

        $externalReference = (string) ($payment['externalReference'] ?? '');

        if ($externalReference !== 'receivable:'.$charge->receivable_id) {
            return true;
        }

        $amountCents = (int) round(((float) ($payment['value'] ?? 0)) * 100);

        if ($amountCents < 1) {
            return true;
        }

        $markReceivablePaidFromGateway->execute($charge->receivable, $paymentId, $amountCents);

        return true;
    }
}
