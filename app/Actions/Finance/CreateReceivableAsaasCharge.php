<?php

namespace App\Actions\Finance;

use App\Billing\TenantAsaasPaymentGateway;
use App\Models\OrganizationPaymentGateway;
use App\Models\Receivable;
use App\Models\ReceivableCharge;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateReceivableAsaasCharge
{
    public function __construct(
        private TenantAsaasPaymentGateway $tenantAsaasPaymentGateway,
    ) {}

    public function execute(Receivable $receivable, string $billingType = ReceivableCharge::TYPE_PIX): ReceivableCharge
    {
        if (! in_array($billingType, [ReceivableCharge::TYPE_PIX, ReceivableCharge::TYPE_BOLETO], true)) {
            throw new InvalidArgumentException('Escolha Pix ou boleto.');
        }

        try {
            return Cache::lock('receivable-charge:'.$receivable->id, 30)
                ->block(10, fn (): ReceivableCharge => $this->createLocked($receivable, $billingType));
        } catch (LockTimeoutException) {
            throw new InvalidArgumentException('Já estamos gerando o Pix desta cobrança. Tente de novo em instantes.');
        }
    }

    private function createLocked(Receivable $receivable, string $billingType): ReceivableCharge
    {
        $receivable = $receivable->fresh(['charge', 'client.contacts', 'organization.paymentGateway'])
            ?? throw new InvalidArgumentException('Cobrança não encontrada.');

        if (! in_array($receivable->status, [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL], true)) {
            throw new InvalidArgumentException('Só é possível gerar Pix para cobranças em aberto.');
        }

        if ($receivable->charge?->isPending()) {
            return $receivable->charge;
        }

        $gateway = $receivable->organization->paymentGateway;

        if ($gateway === null || ! $gateway->isReady()) {
            throw new InvalidArgumentException('Conecte o Asaas da organização em Organizações para gerar o Pix.');
        }

        $customerId = $this->tenantAsaasPaymentGateway->ensureCustomer($gateway, $receivable->client);
        $payment = $this->tenantAsaasPaymentGateway->createPayment(
            $gateway,
            $receivable,
            $customerId,
            $billingType,
        );

        try {
            $charge = DB::transaction(function () use ($receivable, $billingType, $gateway, $payment): ReceivableCharge {
                $locked = Receivable::query()->lockForUpdate()->findOrFail($receivable->id);

                if (! in_array($locked->status, [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL], true)) {
                    throw new InvalidArgumentException('Só é possível gerar Pix para cobranças em aberto.');
                }

                $existing = ReceivableCharge::query()->where('receivable_id', $locked->id)->lockForUpdate()->first();

                if ($existing?->isPending()) {
                    return $existing;
                }

                $created = ReceivableCharge::query()->updateOrCreate(
                    ['receivable_id' => $locked->id],
                    [
                        'organization_id' => $locked->organization_id,
                        'client_id' => $locked->client_id,
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

                $locked->update([
                    'payment_url' => $payment['invoice_url'] ?? $payment['bank_slip_url'],
                    'payment_reference' => $payment['provider_payment_id'],
                ]);

                $gateway->update(['last_error' => null]);

                return $created->fresh();
            });
        } catch (\Throwable $exception) {
            $this->discardAsaasPayment($gateway, $payment['provider_payment_id']);

            throw $exception;
        }

        if ($charge->provider_payment_id !== $payment['provider_payment_id']) {
            $this->discardAsaasPayment($gateway, $payment['provider_payment_id']);
        }

        return $charge;
    }

    private function discardAsaasPayment(OrganizationPaymentGateway $gateway, string $paymentId): void
    {
        try {
            $this->tenantAsaasPaymentGateway->deletePayment($gateway, $paymentId);
        } catch (\Throwable) {
        }
    }
}
