<?php

namespace App\Actions\Contracts;

use App\Models\Contract;
use App\Models\ReceivableRecurrence;
use Illuminate\Support\Carbon;

class SyncContractReceivableRecurrence
{
    public function createFromContract(Contract $contract, int $createdByUserId): ?ReceivableRecurrence
    {
        if (! $this->shouldBill($contract)) {
            return null;
        }

        $existing = $this->existingFor($contract);

        if ($existing) {
            return $this->syncExisting($existing, $contract, activate: true);
        }

        $startDate = $contract->starts_at?->copy() ?? now();
        $billingDay = min(28, max(1, (int) $startDate->day));
        $nextDue = $this->initialDueDate($startDate, $billingDay);

        return ReceivableRecurrence::query()->create([
            'organization_id' => $contract->organization_id,
            'client_id' => $contract->client_id,
            'contract_id' => $contract->id,
            'created_by_user_id' => $createdByUserId,
            'description' => 'Contrato '.$contract->code,
            'amount_cents' => (int) $contract->amount_cents,
            'frequency' => $this->frequencyFor($contract),
            'billing_day' => $billingDay,
            'start_date' => $startDate->toDateString(),
            'end_date' => $contract->ends_at?->toDateString(),
            'next_due_date' => $nextDue->toDateString(),
            'is_active' => true,
            'notes' => 'Gerada a partir do contrato '.$contract->code,
        ]);
    }

    public function syncOnRenew(Contract $contract, int $createdByUserId, bool $createIfMissing): ?ReceivableRecurrence
    {
        $existing = $this->existingFor($contract);

        if ($existing) {
            return $this->syncExisting($existing, $contract, activate: true);
        }

        if (! $createIfMissing) {
            return null;
        }

        return $this->createFromContract($contract, $createdByUserId);
    }

    public function pauseForContract(Contract $contract): void
    {
        ReceivableRecurrence::query()
            ->where('contract_id', $contract->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    private function shouldBill(Contract $contract): bool
    {
        return $contract->status === Contract::STATUS_ACTIVE
            && in_array($contract->billing_interval, [Contract::BILLING_MONTH, Contract::BILLING_YEAR], true)
            && (int) $contract->amount_cents > 0;
    }

    private function frequencyFor(Contract $contract): string
    {
        return $contract->billing_interval === Contract::BILLING_YEAR
            ? ReceivableRecurrence::FREQUENCY_YEARLY
            : ReceivableRecurrence::FREQUENCY_MONTHLY;
    }

    private function existingFor(Contract $contract): ?ReceivableRecurrence
    {
        return ReceivableRecurrence::query()->where('contract_id', $contract->id)->first();
    }

    private function syncExisting(ReceivableRecurrence $recurrence, Contract $contract, bool $activate): ReceivableRecurrence
    {
        $recurrence->update([
            'amount_cents' => (int) $contract->amount_cents > 0
                ? (int) $contract->amount_cents
                : $recurrence->amount_cents,
            'end_date' => $contract->ends_at?->toDateString(),
            'is_active' => $activate ? true : $recurrence->is_active,
            'frequency' => $this->shouldBill($contract)
                ? $this->frequencyFor($contract)
                : $recurrence->frequency,
        ]);

        return $recurrence->fresh();
    }

    private function initialDueDate(Carbon $startDate, int $billingDay): Carbon
    {
        $dueDate = $startDate->copy()->day(min($billingDay, $startDate->daysInMonth));

        if ($dueDate->lt($startDate)) {
            $dueDate = $dueDate->addMonthNoOverflow()->day(min($billingDay, $dueDate->daysInMonth));
        }

        return $dueDate;
    }
}
