<?php

namespace App\Actions\Finance;

use App\Models\Receivable;
use App\Models\ReceivableCharge;
use Illuminate\Support\Facades\DB;

class MarkReceivablePaidFromGateway
{
    public function execute(Receivable $receivable, string $providerPaymentId, int $amountCents): Receivable
    {
        return DB::transaction(function () use ($receivable, $providerPaymentId, $amountCents): Receivable {
            $receivable = Receivable::query()->lockForUpdate()->findOrFail($receivable->id);

            if ($receivable->status === Receivable::STATUS_PAID) {
                $receivable->charge?->update(['status' => ReceivableCharge::STATUS_CONFIRMED]);

                return $receivable->fresh(['charge']);
            }

            if (! in_array($receivable->status, [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL], true)) {
                return $receivable->fresh(['charge']);
            }

            $alreadyRecorded = $receivable->payments()
                ->where('reference', $providerPaymentId)
                ->exists();

            if (! $alreadyRecorded) {
                $applied = min($amountCents, $receivable->balanceCents());

                if ($applied > 0) {
                    $receivable->payments()->create([
                        'organization_id' => $receivable->organization_id,
                        'received_by_user_id' => null,
                        'amount_cents' => $applied,
                        'paid_at' => now()->toDateString(),
                        'method' => 'asaas',
                        'reference' => $providerPaymentId,
                        'notes' => 'Pagamento confirmado pelo Asaas',
                    ]);

                    $paid = $receivable->paid_amount_cents + $applied;
                    $receivable->update([
                        'paid_amount_cents' => $paid,
                        'status' => $paid >= $receivable->amount_cents
                            ? Receivable::STATUS_PAID
                            : Receivable::STATUS_PARTIAL,
                        'paid_at' => $paid >= $receivable->amount_cents ? now()->toDateString() : $receivable->paid_at,
                    ]);
                }
            }

            $receivable->charge?->update(['status' => ReceivableCharge::STATUS_CONFIRMED]);

            return $receivable->fresh(['charge']);
        });
    }
}
