<?php

namespace App\Actions\Contracts;

use App\Models\Contract;
use InvalidArgumentException;

class CancelContract
{
    public function __construct(
        private SyncContractReceivableRecurrence $syncContractReceivableRecurrence,
    ) {}

    public function execute(Contract $contract, ?string $reason = null): Contract
    {
        if ($contract->status === Contract::STATUS_CANCELED) {
            throw new InvalidArgumentException('Contrato já está cancelado.');
        }

        $contract->update([
            'status' => Contract::STATUS_CANCELED,
            'canceled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        $this->syncContractReceivableRecurrence->pauseForContract($contract);

        return $contract->fresh(['client', 'clientServices.serviceType', 'receivableRecurrence']);
    }
}
