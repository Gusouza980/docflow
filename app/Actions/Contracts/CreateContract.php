<?php

namespace App\Actions\Contracts;

use App\Models\Client;
use App\Models\ClientService;
use App\Models\Contract;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateContract
{
    public function __construct(
        private SyncContractReceivableRecurrence $syncContractReceivableRecurrence,
    ) {}

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
     *     client_service_ids?: list<int>,
     *     create_receivable_recurrence?: bool,
     *     created_by_user_id?: int
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

            if (($data['create_receivable_recurrence'] ?? false) === true && isset($data['created_by_user_id'])) {
                $this->syncContractReceivableRecurrence->createFromContract($contract, (int) $data['created_by_user_id']);
            }

            return $contract->fresh(['client', 'clientServices.serviceType', 'receivableRecurrence']);
        });
    }
}
