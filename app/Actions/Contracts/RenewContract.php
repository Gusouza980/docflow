<?php

namespace App\Actions\Contracts;

use App\Models\Contract;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class RenewContract
{
    public function __construct(
        private SyncContractReceivableRecurrence $syncContractReceivableRecurrence,
    ) {}

    public function execute(
        Contract $contract,
        ?Carbon $newEndsAt = null,
        bool $createReceivableRecurrence = false,
        ?int $createdByUserId = null,
    ): Contract {
        if (! in_array($contract->status, [Contract::STATUS_ACTIVE, Contract::STATUS_EXPIRED], true)) {
            throw new InvalidArgumentException('Somente contratos ativos ou expirados podem ser renovados.');
        }

        if ($contract->ends_at === null && $newEndsAt === null) {
            throw new InvalidArgumentException('Informe a nova data de término para renovar.');
        }

        $base = $newEndsAt;

        if ($base === null) {
            $currentEnd = $contract->ends_at?->copy() ?? now();

            $base = match ($contract->billing_interval) {
                Contract::BILLING_YEAR => $currentEnd->copy()->addYear(),
                Contract::BILLING_ONCE => $currentEnd->copy()->addYear(),
                default => $currentEnd->copy()->addMonth(),
            };
        }

        if ($contract->ends_at !== null && $base->lte($contract->ends_at)) {
            throw new InvalidArgumentException('A nova vigência deve ser posterior ao término atual.');
        }

        $contract->update([
            'ends_at' => $base->toDateString(),
            'status' => Contract::STATUS_ACTIVE,
            'canceled_at' => null,
            'cancel_reason' => null,
        ]);

        $contract = $contract->fresh(['client', 'clientServices.serviceType']);

        if ($createdByUserId !== null) {
            $this->syncContractReceivableRecurrence->syncOnRenew(
                $contract,
                $createdByUserId,
                $createReceivableRecurrence,
            );
        }

        return $contract->fresh(['client', 'clientServices.serviceType', 'receivableRecurrence']);
    }
}
