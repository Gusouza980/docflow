<?php

namespace App\Actions\Crm;

use App\Models\Lead;
use InvalidArgumentException;

class UpdateLeadStage
{
    public function execute(Lead $lead, string $stage, ?string $lostReason = null): Lead
    {
        if (! in_array($stage, Lead::stages(), true)) {
            throw new InvalidArgumentException('Etapa de funil inválida.');
        }

        if ($stage === Lead::STAGE_LOST && blank($lostReason)) {
            throw new InvalidArgumentException('Motivo de perda é obrigatório.');
        }

        $lead->update([
            'stage' => $stage,
            'lost_reason' => $stage === Lead::STAGE_LOST ? $lostReason : null,
        ]);

        return $lead->fresh();
    }
}
