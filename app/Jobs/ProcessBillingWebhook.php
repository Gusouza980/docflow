<?php

namespace App\Jobs;

use App\Actions\Billing\MarkInvoicePaid;
use App\Actions\Billing\MarkSubscriptionPastDue;
use App\Models\BillingWebhookEvent;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ProcessBillingWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $provider,
        public string $eventId,
        public array $payload,
    ) {}

    public function handle(
        MarkInvoicePaid $markInvoicePaid,
        MarkSubscriptionPastDue $markSubscriptionPastDue,
    ): void {
        $event = BillingWebhookEvent::query()->firstOrCreate(
            ['provider' => $this->provider, 'event_id' => $this->eventId],
            ['payload' => $this->payload],
        );

        if ($event->isProcessed()) {
            return;
        }

        DB::transaction(function () use ($event, $markInvoicePaid, $markSubscriptionPastDue): void {
            if ($this->provider === 'asaas') {
                $this->handleAsaas($markInvoicePaid, $markSubscriptionPastDue);
            } else {
                $this->handleManual($markInvoicePaid);
            }

            $event->update(['processed_at' => now()]);
        });
    }

    private function handleManual(MarkInvoicePaid $markInvoicePaid): void
    {
        $eventType = (string) ($this->payload['event'] ?? '');

        if (! in_array($eventType, ['invoice.paid', 'payment.confirmed'], true)) {
            return;
        }

        $invoiceId = (int) ($this->payload['invoice_id'] ?? 0);
        $invoice = SubscriptionInvoice::query()->find($invoiceId);

        if ($invoice !== null) {
            $markInvoicePaid->execute($invoice, paymentMethod: $this->provider);
        }
    }

    private function handleAsaas(
        MarkInvoicePaid $markInvoicePaid,
        MarkSubscriptionPastDue $markSubscriptionPastDue,
    ): void {
        $eventType = (string) ($this->payload['event'] ?? '');
        /** @var array<string, mixed> $payment */
        $payment = is_array($this->payload['payment'] ?? null) ? $this->payload['payment'] : [];

        $invoice = $this->resolveInvoiceFromAsaasPayment($payment);

        if ($invoice === null) {
            return;
        }

        if (in_array($eventType, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true)) {
            $markInvoicePaid->execute($invoice, paymentMethod: 'asaas');

            return;
        }

        if ($eventType === 'PAYMENT_OVERDUE') {
            $markSubscriptionPastDue->execute($invoice->subscription->organization);
        }
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function resolveInvoiceFromAsaasPayment(array $payment): ?SubscriptionInvoice
    {
        $paymentId = (string) ($payment['id'] ?? '');

        if ($paymentId !== '') {
            $byProviderId = SubscriptionInvoice::query()
                ->where('provider_invoice_id', $paymentId)
                ->first();

            if ($byProviderId !== null) {
                return $byProviderId;
            }
        }

        $externalReference = (string) ($payment['externalReference'] ?? '');

        if (str_starts_with($externalReference, 'invoice:')) {
            $invoiceId = (int) substr($externalReference, strlen('invoice:'));

            return SubscriptionInvoice::query()->find($invoiceId);
        }

        $providerSubscriptionId = (string) ($payment['subscription'] ?? '');

        if ($providerSubscriptionId === '') {
            return null;
        }

        $subscription = Subscription::query()
            ->where('provider_subscription_id', $providerSubscriptionId)
            ->first();

        if ($subscription === null) {
            return null;
        }

        return SubscriptionInvoice::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', SubscriptionInvoice::STATUS_OPEN)
            ->latest('id')
            ->first();
    }
}
