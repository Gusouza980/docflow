<?php

namespace App\Actions\Finance;

use App\Models\Organization;
use App\Models\OrganizationPaymentGateway;

class SaveOrganizationPaymentGateway
{
    /**
     * @param  array{api_key?: string, webhook_token?: string, is_enabled?: bool}  $data
     */
    public function execute(Organization $organization, array $data): OrganizationPaymentGateway
    {
        $existing = $organization->paymentGateway;

        $attributes = [
            'provider' => OrganizationPaymentGateway::PROVIDER_ASAAS,
            'is_enabled' => $data['is_enabled'] ?? true,
            'connected_at' => now(),
            'last_error' => null,
        ];

        if (isset($data['api_key']) && $data['api_key'] !== '') {
            $attributes['api_key'] = $data['api_key'];
        } elseif ($existing) {
            $attributes['api_key'] = $existing->api_key;
        }

        if (isset($data['webhook_token']) && $data['webhook_token'] !== '') {
            $attributes['webhook_token'] = $data['webhook_token'];
        } elseif ($existing) {
            $attributes['webhook_token'] = $existing->webhook_token;
        }

        if (! isset($attributes['api_key']) || $attributes['api_key'] === '') {
            throw new \InvalidArgumentException('Informe a chave de API do Asaas.');
        }

        if (! isset($attributes['webhook_token']) || $attributes['webhook_token'] === '') {
            throw new \InvalidArgumentException('Informe o token do webhook do Asaas.');
        }

        return OrganizationPaymentGateway::query()->updateOrCreate(
            ['organization_id' => $organization->id],
            $attributes,
        );
    }
}
