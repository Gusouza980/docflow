<?php

namespace App\Actions\Contracts;

use App\Models\Client;
use App\Models\ClientService;
use App\Models\ServiceType;
use InvalidArgumentException;

class UpsertClientService
{
    /**
     * @param  array{
     *     service_type_id: int,
     *     status?: string,
     *     starts_at?: ?string,
     *     ends_at?: ?string,
     *     assigned_to_member_id?: ?int,
     *     notes?: ?string
     * }  $data
     */
    public function execute(Client $client, array $data, ?ClientService $clientService = null): ClientService
    {
        $serviceType = ServiceType::query()->findOrFail($data['service_type_id']);

        if ($serviceType->organization_id !== $client->organization_id) {
            throw new InvalidArgumentException('Tipo de serviço pertence a outra organização.');
        }

        if (! $serviceType->is_active && $clientService === null) {
            throw new InvalidArgumentException('Tipo de serviço inativo.');
        }

        if ($clientService !== null) {
            if ($clientService->client_id !== $client->id) {
                throw new InvalidArgumentException('Serviço não pertence a este cliente.');
            }

            $clientService->update([
                'service_type_id' => $serviceType->id,
                'status' => $data['status'] ?? $clientService->status,
                'starts_at' => array_key_exists('starts_at', $data) ? $data['starts_at'] : $clientService->starts_at,
                'ends_at' => array_key_exists('ends_at', $data) ? $data['ends_at'] : $clientService->ends_at,
                'assigned_to_member_id' => array_key_exists('assigned_to_member_id', $data)
                    ? $data['assigned_to_member_id']
                    : $clientService->assigned_to_member_id,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $clientService->notes,
            ]);

            return $clientService->fresh(['serviceType', 'assignee.user']);
        }

        return ClientService::query()->create([
            'organization_id' => $client->organization_id,
            'client_id' => $client->id,
            'service_type_id' => $serviceType->id,
            'status' => $data['status'] ?? ClientService::STATUS_ACTIVE,
            'starts_at' => $data['starts_at'] ?? now()->toDateString(),
            'ends_at' => $data['ends_at'] ?? null,
            'assigned_to_member_id' => $data['assigned_to_member_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ])->load(['serviceType', 'assignee.user']);
    }
}
