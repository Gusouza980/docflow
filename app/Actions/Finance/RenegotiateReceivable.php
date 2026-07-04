<?php

namespace App\Actions\Finance;

use App\Models\Receivable;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RenegotiateReceivable
{
    /**
     * @param  array{amount_cents: int, due_at: string, description?: string|null, notes?: string|null}  $data
     */
    public function execute(Receivable $receivable, User $user, string $reason, array $data): Receivable
    {
        abort_unless($receivable->canBeRenegotiated(), 422);

        return DB::transaction(function () use ($receivable, $user, $reason, $data): Receivable {
            $replacement = Receivable::create([
                'organization_id' => $receivable->organization_id,
                'client_id' => $receivable->client_id,
                'financial_category_id' => $receivable->financial_category_id,
                'created_by_user_id' => $user->id,
                'description' => $data['description'] ?? $receivable->description,
                'amount_cents' => $data['amount_cents'],
                'due_at' => $data['due_at'],
                'competence_date' => $data['competence_date'] ?? $receivable->competence_date,
                'notes' => $data['notes'] ?? $receivable->notes,
                'renegotiated_from_receivable_id' => $receivable->id,
            ]);

            $receivable->update([
                'status' => Receivable::STATUS_RENEGOTIATED,
                'renegotiated_to_receivable_id' => $replacement->id,
                'renegotiation_reason' => $reason,
                'renegotiated_at' => now(),
            ]);

            return $replacement;
        });
    }
}
