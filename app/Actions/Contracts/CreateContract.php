<?php

namespace App\Actions\Contracts;

use App\Models\Client;
use App\Models\ClientService;
use App\Models\Contract;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateContract
{
    /**
     * @param  array{
     *     code: string,
     *     status?: string,
     *     amount_cents?: ?int,
     *     billing_interval: string,
     *     starts_at: string,
     *     ends_at?: ?string,
     *     auto_renew?: bool,
     *     scope_included?: ?string,
     *     scope_excluded?: ?string,
     *     client_service_ids?: list<int>
     * }  $data
     */
    public function execute(Client $client, array $data): Contract
    {
        return DB::transaction(function () use ($client, $data): Contract {
            $contract = Contract::query()->create([
                'organization_id' => $client->organization_id,
                'client_id' => $client->id,
                'code' => $data['code'],
                'status' => $data['status'] ?? Contract::STATUS_DRAFT,
                'amount_cents' => $data['amount_cents'] ?? null,
                'billing_interval' => $data['billing_interval'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'] ?? null,
                'auto_renew' => $data['auto_renew'] ?? false,
                'scope_included' => $data['scope_included'] ?? null,
                'scope_excluded' => $data['scope_excluded'] ?? null,
            ]);

            $serviceIds = $data['client_service_ids'] ?? [];

            if ($serviceIds !== []) {
                $validIds = ClientService::query()
                    ->where('organization_id', $client->organization_id)
                    ->where('client_id', $client->id)
                    ->whereIn('id', $serviceIds)
                    ->pluck('id')
                    ->all();

                if (count($validIds) !== count($serviceIds)) {
                    throw new InvalidArgumentException('Um ou mais serviços não pertencem a este cliente.');
                }

                $contract->clientServices()->sync($validIds);
            }

            return $contract->fresh(['client', 'clientServices.serviceType']);
        });
    }
}
