<?php

namespace App\Actions\Contracts;

use App\Models\Contract;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ActivateContract
{
    public function __construct(
        private SyncContractReceivableRecurrence $syncContractReceivableRecurrence,
    ) {}

    public function execute(Contract $contract, int $createdByUserId): Contract
    {
        if ($contract->status !== Contract::STATUS_DRAFT) {
            throw new InvalidArgumentException('Somente contratos em rascunho podem ser ativados.');
        }

        return DB::transaction(function () use ($contract, $createdByUserId): Contract {
            $contract->update(['status' => Contract::STATUS_ACTIVE]);
            $contract = $contract->fresh();

            if ($contract->creates_receivable_recurrence) {
                $this->syncContractReceivableRecurrence->createFromContract($contract, $createdByUserId);
            }

            return $contract->fresh(['client', 'clientServices.serviceType', 'receivableRecurrence']);
        });
    }
}
