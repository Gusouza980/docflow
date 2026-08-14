<?php

namespace App\Actions\Finance;

use App\Models\Receivable;
use App\Models\ReceivableRecurrence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateReceivableRecurrences
{
    /**
     * @return list<Receivable>
     */
    public function execute(?ReceivableRecurrence $recurrence = null): array
    {
        $query = ReceivableRecurrence::query()
            ->where('is_active', true)
            ->when($recurrence, fn ($builder) => $builder->whereKey($recurrence->id));

        $generated = [];

        $query->orderBy('id')->each(function (ReceivableRecurrence $item) use (&$generated): void {
            while ($item->fresh()->isDueForGeneration()) {
                $created = $this->generateForRecurrence($item);

                if ($created === null) {
                    break;
                }

                $generated[] = $created;
                $item = $item->fresh();
            }
        });

        return $generated;
    }

    private function generateForRecurrence(ReceivableRecurrence $recurrence): ?Receivable
    {
        return DB::transaction(function () use ($recurrence): ?Receivable {
            $recurrence = ReceivableRecurrence::query()->lockForUpdate()->find($recurrence->id);

            if (! $recurrence || ! $recurrence->isDueForGeneration()) {
                return null;
            }

            $dueDate = $recurrence->next_due_date->copy();
            $billingPeriod = $dueDate->copy()->startOfMonth()->toDateString();

            $existing = Receivable::query()
                ->where('receivable_recurrence_id', $recurrence->id)
                ->whereDate('billing_period', $billingPeriod)
                ->first();

            if ($existing) {
                $recurrence->update([
                    'next_due_date' => $this->nextDueDate($recurrence)->toDateString(),
                ]);

                return null;
            }

            $receivable = Receivable::create([
                'organization_id' => $recurrence->organization_id,
                'client_id' => $recurrence->client_id,
                'financial_category_id' => $recurrence->financial_category_id,
                'created_by_user_id' => $recurrence->created_by_user_id,
                'receivable_recurrence_id' => $recurrence->id,
                'description' => $recurrence->description,
                'amount_cents' => $recurrence->amount_cents,
                'due_at' => $dueDate->toDateString(),
                'competence_date' => $billingPeriod,
                'billing_period' => $billingPeriod,
                'notes' => $recurrence->notes,
            ]);

            $recurrence->update([
                'next_due_date' => $this->nextDueDate($recurrence)->toDateString(),
            ]);

            return $receivable;
        });
    }

    private function nextDueDate(ReceivableRecurrence $recurrence): Carbon
    {
        $next = $recurrence->frequency === ReceivableRecurrence::FREQUENCY_YEARLY
            ? $recurrence->next_due_date->copy()->addYear()
            : $recurrence->next_due_date->copy()->addMonthNoOverflow();
        $day = min($recurrence->billing_day, $next->daysInMonth);

        return $next->day($day);
    }
}
