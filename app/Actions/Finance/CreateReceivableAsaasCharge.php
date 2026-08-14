<?php

namespace App\Actions\Finance;

use App\Billing\TenantAsaasPaymentGateway;
use App\Models\Receivable;
use App\Models\ReceivableCharge;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateReceivableAsaasCharge
{
    public function __construct(
        private TenantAsaasPaymentGateway $tenantAsaasPaymentGateway,
    ) {}

    public function execute(Receivable $receivable, string $billingType = ReceivableCharge::TYPE_PIX): ReceivableCharge
    {
        if (! in_array($receivable->status, [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL], true)) {
            throw new InvalidArgumentException('Só é possível gerar Pix para cobranças em aberto.');
        }

        if (! in_array($billingType, [ReceivableCharge::TYPE_PIX, ReceivableCharge::TYPE_BOLETO], true)) {
            throw new InvalidArgumentException('Escolha Pix ou boleto.');
        }

        $receivable->loadMissing(['charge', 'client.contacts', 'organization.paymentGateway']);

        $existing = $receivable->charge;

        if ($existing && $existing->isPending()) {
            return $existing;
        }

        $gateway = $receivable->organization->paymentGateway;

        if ($gateway === null || ! $gateway->isReady()) {
            throw new InvalidArgumentException('Conecte o Asaas da organização em Organizações para gerar o Pix.');
        }

        return DB::transaction(function () use ($receivable, $billingType, $gateway): ReceivableCharge {
            $customerId = $this->tenantAsaasPaymentGateway->ensureCustomer($gateway, $receivable->client);
            $payment = $this->tenantAsaasPaymentGateway->createPayment(
                $gateway,
                $receivable,
                $customerId,
                $billingType,
            );

            $charge = ReceivableCharge::query()->updateOrCreate(
                ['receivable_id' => $receivable->id],
                [
                    'organization_id' => $receivable->organization_id,
                    'client_id' => $receivable->client_id,
                    'provider' => ReceivableCharge::PROVIDER_ASAAS,
                    'provider_payment_id' => $payment['provider_payment_id'],
                    'billing_type' => $billingType,
                    'status' => ReceivableCharge::STATUS_PENDING,
                    'invoice_url' => $payment['invoice_url'],
                    'pix_payload' => $payment['pix_payload'],
                    'pix_encoded_image' => $payment['pix_encoded_image'],
                    'bank_slip_url' => $payment['bank_slip_url'],
                    'identification_field' => $payment['identification_field'],
                    'last_synced_at' => now(),
                ],
            );

            $receivable->update([
                'payment_url' => $payment['invoice_url'] ?? $payment['bank_slip_url'],
                'payment_reference' => $payment['provider_payment_id'],
            ]);

            $gateway->update(['last_error' => null]);

            return $charge->fresh();
        });
    }
}
