<?php

namespace App\Jobs;

use App\Actions\Billing\MarkInvoicePaid;
use App\Models\BillingWebhookEvent;
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

    public function handle(MarkInvoicePaid $markInvoicePaid): void
    {
        $event = BillingWebhookEvent::query()->firstOrCreate(
            ['provider' => $this->provider, 'event_id' => $this->eventId],
            ['payload' => $this->payload],
        );

        if ($event->isProcessed()) {
            return;
        }

        DB::transaction(function () use ($event, $markInvoicePaid): void {
            $eventType = (string) ($this->payload['event'] ?? '');

            if (in_array($eventType, ['invoice.paid', 'payment.confirmed'], true)) {
                $invoiceId = (int) ($this->payload['invoice_id'] ?? 0);
                $invoice = SubscriptionInvoice::query()->find($invoiceId);

                if ($invoice !== null) {
                    $markInvoicePaid->execute($invoice, paymentMethod: $this->provider);
                }
            }

            $event->update(['processed_at' => now()]);
        });
    }
}
