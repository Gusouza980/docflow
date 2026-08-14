<?php

namespace App\Billing;

use App\Billing\Asaas\AsaasClient;
use App\Exceptions\AsaasApiException;
use App\Models\Client;
use App\Models\OrganizationPaymentGateway;
use App\Models\Receivable;
use App\Models\ReceivableCharge;
use InvalidArgumentException;

class TenantAsaasPaymentGateway
{
    public function clientFor(OrganizationPaymentGateway $gateway): AsaasClient
    {
        return AsaasClient::forTenant($gateway->api_key);
    }

    public function ensureCustomer(OrganizationPaymentGateway $gateway, Client $client): string
    {
        if (is_string($client->asaas_customer_id) && $client->asaas_customer_id !== '') {
            return $client->asaas_customer_id;
        }

        $document = preg_replace('/\D+/', '', (string) $client->document_number) ?? '';

        if (strlen($document) < 11) {
            throw new InvalidArgumentException('Cadastre o CPF/CNPJ do cliente antes de gerar o Pix.');
        }

        $payload = [
            'name' => $client->display_name,
            'cpfCnpj' => $document,
            'externalReference' => 'client:'.$client->id,
        ];

        $email = $client->contacts()->where('is_primary', true)->value('email')
            ?? $client->contacts()->whereNotNull('email')->value('email');

        if (is_string($email) && $email !== '') {
            $payload['email'] = $email;
        }

        $response = $this->clientFor($gateway)->post('/v3/customers', $payload);
        $customerId = (string) ($response['id'] ?? '');

        if ($customerId === '') {
            throw new AsaasApiException('Asaas não retornou o cliente da organização.');
        }

        $client->update(['asaas_customer_id' => $customerId]);

        return $customerId;
    }

    /**
     * @return array{
     *     provider_payment_id: string,
     *     invoice_url: ?string,
     *     pix_payload: ?string,
     *     pix_encoded_image: ?string,
     *     bank_slip_url: ?string,
     *     identification_field: ?string
     * }
     */
    public function createPayment(
        OrganizationPaymentGateway $gateway,
        Receivable $receivable,
        string $customerId,
        string $billingType,
    ): array {
        $value = round($receivable->balanceCents() / 100, 2);

        if ($value <= 0) {
            throw new InvalidArgumentException('Não há saldo em aberto para gerar cobrança.');
        }

        $payload = [
            'customer' => $customerId,
            'billingType' => $billingType,
            'value' => $value,
            'dueDate' => ($receivable->due_at ?? now())->toDateString(),
            'description' => $receivable->description,
            'externalReference' => 'receivable:'.$receivable->id,
        ];

        $payment = $this->clientFor($gateway)->post('/v3/payments', $payload);
        $paymentId = (string) ($payment['id'] ?? '');

        if ($paymentId === '') {
            throw new AsaasApiException('Asaas não retornou o identificador da cobrança.');
        }

        $pixPayload = null;
        $pixImage = null;

        if ($billingType === ReceivableCharge::TYPE_PIX) {
            $qr = $this->clientFor($gateway)->get('/v3/payments/'.$paymentId.'/pixQrCode');
            $pixPayload = isset($qr['payload']) ? (string) $qr['payload'] : null;
            $pixImage = isset($qr['encodedImage']) ? (string) $qr['encodedImage'] : null;
        }

        return [
            'provider_payment_id' => $paymentId,
            'invoice_url' => isset($payment['invoiceUrl']) ? (string) $payment['invoiceUrl'] : null,
            'pix_payload' => $pixPayload,
            'pix_encoded_image' => $pixImage,
            'bank_slip_url' => isset($payment['bankSlipUrl']) ? (string) $payment['bankSlipUrl'] : null,
            'identification_field' => isset($payment['identificationField']) ? (string) $payment['identificationField'] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchConfirmedPayment(OrganizationPaymentGateway $gateway, string $paymentId): ?array
    {
        $payment = $this->clientFor($gateway)->get('/v3/payments/'.$paymentId);
        $status = (string) ($payment['status'] ?? '');

        if (! in_array($status, ['RECEIVED', 'CONFIRMED'], true)) {
            return null;
        }

        return $payment;
    }

    public function deletePayment(OrganizationPaymentGateway $gateway, string $paymentId): void
    {
        $this->clientFor($gateway)->delete('/v3/payments/'.$paymentId);
    }
}
