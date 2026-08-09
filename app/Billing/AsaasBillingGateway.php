<?php

namespace App\Billing;

use App\Billing\Asaas\AsaasClient;
use App\Contracts\Billing\BillingGateway;
use App\Exceptions\AsaasApiException;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use Illuminate\Support\Facades\Log;

class AsaasBillingGateway implements BillingGateway
{
    public function __construct(private AsaasClient $client) {}

    public function createCustomer(Organization $organization): ?string
    {
        $document = preg_replace('/\D+/', '', (string) $organization->document) ?: null;

        if ($document === null || strlen($document) < 11) {
            throw new AsaasApiException(
                'Organization document (CPF/CNPJ) is required to create an Asaas customer.',
                context: ['organization_id' => $organization->id],
            );
        }

        $payload = [
            'name' => $organization->name,
            'cpfCnpj' => $document,
            'email' => $organization->email,
            'phone' => $organization->phone,
            'externalReference' => 'organization:'.$organization->id,
            'notificationDisabled' => false,
        ];

        $response = $this->client->post('/v3/customers', array_filter(
            $payload,
            fn (mixed $value): bool => $value !== null && $value !== '',
        ));

        $customerId = (string) ($response['id'] ?? '');

        if ($customerId === '') {
            throw new AsaasApiException(
                'Asaas customer response missing id.',
                context: ['organization_id' => $organization->id, 'response' => $response],
            );
        }

        return $customerId;
    }

    public function createSubscription(Subscription $subscription, ?string $nextDueDate = null): ?string
    {
        $subscription->loadMissing(['organization', 'plan']);

        $organization = $subscription->organization;
        $plan = $subscription->plan;

        if ($organization === null || $plan === null) {
            throw new AsaasApiException(
                'Subscription requires organization and plan to create Asaas subscription.',
                context: ['subscription_id' => $subscription->id],
            );
        }

        $customerId = $subscription->provider_customer_id;

        if ($customerId === null || $customerId === '') {
            $customerId = $this->createCustomer($organization);
            $subscription->update([
                'provider_customer_id' => $customerId,
                'billing_provider' => Subscription::BILLING_PROVIDER_ASAAS,
            ]);
        }

        $nextDueDate ??= $subscription->current_period_start?->toDateString()
            ?? now()->toDateString();
        $cycle = $plan->billing_interval === 'year' ? 'YEARLY' : 'MONTHLY';
        $value = round(((int) $plan->price_cents) / 100, 2);

        $response = $this->client->post('/v3/subscriptions', [
            'customer' => $customerId,
            'billingType' => (string) config('docflow.billing.asaas_billing_type', 'UNDEFINED'),
            'nextDueDate' => $nextDueDate,
            'value' => $value,
            'cycle' => $cycle,
            'description' => 'Docflow — '.$plan->name,
            'externalReference' => 'subscription:'.$subscription->id,
        ]);

        $providerSubscriptionId = (string) ($response['id'] ?? '');

        if ($providerSubscriptionId === '') {
            throw new AsaasApiException(
                'Asaas subscription response missing id.',
                context: ['subscription_id' => $subscription->id, 'response' => $response],
            );
        }

        $subscription->update([
            'provider_subscription_id' => $providerSubscriptionId,
            'billing_provider' => Subscription::BILLING_PROVIDER_ASAAS,
        ]);

        return $providerSubscriptionId;
    }

    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd = false): void
    {
        $providerSubscriptionId = $subscription->provider_subscription_id;

        if ($providerSubscriptionId === null || $providerSubscriptionId === '') {
            return;
        }

        try {
            if ($atPeriodEnd) {
                // Keep current cycle charges; stop future renewals.
                $this->client->put('/v3/subscriptions/'.$providerSubscriptionId, [
                    'status' => 'INACTIVE',
                ]);

                return;
            }

            $this->client->delete('/v3/subscriptions/'.$providerSubscriptionId);
        } catch (AsaasApiException $exception) {
            // Do not block local cancellation when Asaas is unavailable.
            Log::warning('Failed to cancel Asaas subscription', [
                'subscription_id' => $subscription->id,
                'provider_subscription_id' => $providerSubscriptionId,
                'at_period_end' => $atPeriodEnd,
                'status' => $exception->status,
            ]);
        }
    }

    public function createInvoice(SubscriptionInvoice $invoice): ?string
    {
        $invoice->loadMissing('subscription.plan', 'subscription.organization');

        $subscription = $invoice->subscription;

        if ($subscription === null) {
            throw new AsaasApiException(
                'Invoice requires a subscription to sync Asaas payment.',
                context: ['invoice_id' => $invoice->id],
            );
        }

        if ($subscription->provider_subscription_id === null || $subscription->provider_subscription_id === '') {
            $this->createSubscription(
                $subscription->fresh(['organization', 'plan']),
                $invoice->due_at?->toDateString() ?? now()->toDateString(),
            );
            $subscription->refresh();
        }

        $providerSubscriptionId = (string) $subscription->provider_subscription_id;

        $paymentId = $this->findPaymentIdForInvoice($providerSubscriptionId, $invoice);

        if ($paymentId === null) {
            throw new AsaasApiException(
                'No Asaas payment found for subscription invoice.',
                context: [
                    'invoice_id' => $invoice->id,
                    'provider_subscription_id' => $providerSubscriptionId,
                ],
            );
        }

        $this->client->put('/v3/payments/'.$paymentId, [
            'externalReference' => 'invoice:'.$invoice->id,
        ]);

        return $paymentId;
    }

    private function findPaymentIdForInvoice(string $providerSubscriptionId, SubscriptionInvoice $invoice): ?string
    {
        $response = $this->client->get('/v3/subscriptions/'.$providerSubscriptionId.'/payments', [
            'limit' => 20,
        ]);

        /** @var list<array<string, mixed>> $payments */
        $payments = $response['data'] ?? [];
        $expectedValue = round(((int) $invoice->amount_cents) / 100, 2);
        $invoiceDueDate = $invoice->due_at?->toDateString();

        $claimedPaymentIds = SubscriptionInvoice::query()
            ->whereNotNull('provider_invoice_id')
            ->where('id', '!=', $invoice->id)
            ->pluck('provider_invoice_id')
            ->all();

        foreach ($payments as $payment) {
            $externalReference = (string) ($payment['externalReference'] ?? '');

            if ($externalReference === 'invoice:'.$invoice->id) {
                return (string) $payment['id'];
            }
        }

        $candidates = [];

        foreach ($payments as $payment) {
            $paymentId = (string) ($payment['id'] ?? '');
            $status = (string) ($payment['status'] ?? '');
            $externalReference = (string) ($payment['externalReference'] ?? '');
            $value = isset($payment['value']) ? round((float) $payment['value'], 2) : null;
            $dueDate = isset($payment['dueDate']) ? (string) $payment['dueDate'] : null;

            if ($paymentId === '' || in_array($paymentId, $claimedPaymentIds, true)) {
                continue;
            }

            if (! in_array($status, ['PENDING', 'OVERDUE'], true)) {
                continue;
            }

            if ($externalReference !== '' && ! str_starts_with($externalReference, 'subscription:')) {
                continue;
            }

            if ($value !== null && abs($value - $expectedValue) > 0.009) {
                continue;
            }

            $candidates[] = [
                'id' => $paymentId,
                'due_date_matches' => $invoiceDueDate !== null && $dueDate === $invoiceDueDate,
            ];
        }

        foreach ($candidates as $candidate) {
            if ($candidate['due_date_matches']) {
                return $candidate['id'];
            }
        }

        return count($candidates) === 1 ? $candidates[0]['id'] : null;
    }
}
