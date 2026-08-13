<?php

namespace App\Actions\Crm;

use App\Models\Lead;
use InvalidArgumentException;

class UpdateLeadStage
{
    public function execute(Lead $lead, string $stage, ?string $lostReason = null): Lead
    {
        if ($lead->isConverted()) {
            throw new InvalidArgumentException('Leads convertidos não podem mudar de etapa.');
        }

        if ($stage === Lead::STAGE_WON) {
            throw new InvalidArgumentException('Para marcar como aceito, converta o lead em cliente.');
        }

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
