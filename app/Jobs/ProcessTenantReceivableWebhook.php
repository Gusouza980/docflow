<?php

namespace App\Jobs;

use App\Actions\Finance\MarkReceivablePaidFromGateway;
use App\Billing\TenantAsaasPaymentGateway;
use App\Models\Organization;
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

    public function handle(
        MarkReceivablePaidFromGateway $markReceivablePaidFromGateway,
        TenantAsaasPaymentGateway $tenantAsaasPaymentGateway,
    ): void {
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

        $eventName = (string) ($this->payload['event'] ?? '');

        if (! in_array($eventName, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true)) {
            $event->update(['processed_at' => now()]);

            return;
        }

        /** @var array<string, mixed> $notifiedPayment */
        $notifiedPayment = is_array($this->payload['payment'] ?? null) ? $this->payload['payment'] : [];
        $paymentId = (string) ($notifiedPayment['id'] ?? '');

        if ($paymentId === '') {
            $event->update(['processed_at' => now()]);

            return;
        }

        $charge = ReceivableCharge::query()
            ->where('organization_id', $this->organizationId)
            ->where('provider_payment_id', $paymentId)
            ->first();

        if ($charge === null) {
            $event->update(['processed_at' => now()]);

            return;
        }

        $organization = Organization::query()->with('paymentGateway')->find($this->organizationId);
        $gateway = $organization?->paymentGateway;

        if ($gateway === null || ! $gateway->isReady()) {
            throw new RuntimeException('Tenant Asaas gateway is not ready.');
        }

        $confirmed = $tenantAsaasPaymentGateway->fetchConfirmedPayment($gateway, $paymentId);

        if ($confirmed === null) {
            throw new RuntimeException('Asaas payment is not confirmed yet.');
        }

        $externalReference = (string) ($confirmed['externalReference'] ?? '');

        if ($externalReference !== 'receivable:'.$charge->receivable_id) {
            $event->update(['processed_at' => now()]);

            return;
        }

        $amountCents = (int) round(((float) ($confirmed['value'] ?? 0)) * 100);

        if ($amountCents < 1) {
            $event->update(['processed_at' => now()]);

            return;
        }

        DB::transaction(function () use ($event, $markReceivablePaidFromGateway, $charge, $paymentId, $amountCents): void {
            $markReceivablePaidFromGateway->execute($charge->receivable, $paymentId, $amountCents);
            $event->update(['processed_at' => now()]);
        });
    }
}
