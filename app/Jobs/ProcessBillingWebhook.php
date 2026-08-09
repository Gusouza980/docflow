<?php

namespace App\Jobs;

use App\Actions\Billing\MarkInvoicePaid;
use App\Actions\Billing\MarkSubscriptionPastDue;
use App\Models\BillingWebhookEvent;
use App\Models\SubscriptionInvoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
            $applied = match ($this->provider) {
                'asaas' => $this->handleAsaas($markInvoicePaid, $markSubscriptionPastDue),
                default => $this->handleManual($markInvoicePaid),
            };

            if (! $applied) {
                throw new RuntimeException(
                    "Billing webhook [{$this->provider}:{$this->eventId}] could not be applied yet."
                );
            }

            $event->update(['processed_at' => now()]);
        });
    }

    private function handleManual(MarkInvoicePaid $markInvoicePaid): bool
    {
        $eventType = (string) ($this->payload['event'] ?? '');

        if (! in_array($eventType, ['invoice.paid', 'payment.confirmed'], true)) {
            return true;
        }

        $invoiceId = (int) ($this->payload['invoice_id'] ?? 0);
        $invoice = SubscriptionInvoice::query()->find($invoiceId);

        if ($invoice === null) {
            return false;
        }

        $markInvoicePaid->execute($invoice, paymentMethod: $this->provider);

        return true;
    }

    private function handleAsaas(
        MarkInvoicePaid $markInvoicePaid,
        MarkSubscriptionPastDue $markSubscriptionPastDue,
    ): bool {
        $eventType = (string) ($this->payload['event'] ?? '');
        /** @var array<string, mixed> $payment */
        $payment = is_array($this->payload['payment'] ?? null) ? $this->payload['payment'] : [];

        if (! in_array($eventType, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED', 'PAYMENT_OVERDUE'], true)) {
            return true;
        }

        $invoice = $this->resolveInvoiceFromAsaasPayment($payment);

        if ($invoice === null) {
            return false;
        }

        if (in_array($eventType, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true)) {
            $markInvoicePaid->execute($invoice, paymentMethod: 'asaas');

            return true;
        }

        if ($invoice->status === SubscriptionInvoice::STATUS_PAID || ! $invoice->isOpen()) {
            return true;
        }

        $markSubscriptionPastDue->execute($invoice->subscription->organization);

        return true;
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function resolveInvoiceFromAsaasPayment(array $payment): ?SubscriptionInvoice
    {
        $paymentId = (string) ($payment['id'] ?? '');
        $providerSubscriptionId = (string) ($payment['subscription'] ?? '');
        $paymentValue = isset($payment['value']) ? round((float) $payment['value'], 2) : null;

        if ($paymentId === '' || $providerSubscriptionId === '' || $paymentValue === null) {
            return null;
        }

        $byProviderId = SubscriptionInvoice::query()
            ->where('provider_invoice_id', $paymentId)
            ->first();

        if ($byProviderId !== null
            && $this->paymentMatchesInvoice($byProviderId, $providerSubscriptionId, $paymentValue)) {
            return $byProviderId;
        }

        $externalReference = (string) ($payment['externalReference'] ?? '');

        if (! str_starts_with($externalReference, 'invoice:')) {
            return null;
        }

        $invoiceId = (int) substr($externalReference, strlen('invoice:'));
        $byReference = SubscriptionInvoice::query()->find($invoiceId);

        if ($byReference === null
            || ! $this->paymentMatchesInvoice($byReference, $providerSubscriptionId, $paymentValue)) {
            return null;
        }

        $existingProviderId = (string) ($byReference->provider_invoice_id ?? '');

        if ($existingProviderId !== '' && ! hash_equals($existingProviderId, $paymentId)) {
            return null;
        }

        return $byReference;
    }

    private function paymentMatchesInvoice(
        SubscriptionInvoice $invoice,
        string $providerSubscriptionId,
        float $paymentValue,
    ): bool {
        $invoice->loadMissing('subscription');

        $subscriptionProviderId = (string) ($invoice->subscription?->provider_subscription_id ?? '');

        if ($subscriptionProviderId === '' || ! hash_equals($subscriptionProviderId, $providerSubscriptionId)) {
            return false;
        }

        $expected = round(((int) $invoice->amount_cents) / 100, 2);

        return abs($expected - $paymentValue) <= 0.009;
    }
}
