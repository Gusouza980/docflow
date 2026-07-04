<?php

namespace App\Actions\Finance;

use App\Actions\Notifications\NotifyPortalClient;
use App\Models\PortalClientAlert;
use App\Models\Receivable;
use App\Support\DisplayFormat;
use App\Support\ReportLabels;

class NotifyOverdueReceivableToClient
{
    public function __construct(
        private NotifyPortalClient $notifyPortalClient,
    ) {}

    public function execute(Receivable $receivable, bool $ignoreCooldown = false): bool
    {
        if (! $receivable->isOverdue() || ! $receivable->client_id) {
            return false;
        }

        $cooldownDays = (int) config('docflow.finance.overdue_portal_reminder_cooldown_days', 7);

        if (
            ! $ignoreCooldown
            && $receivable->last_portal_reminder_at?->greaterThan(now()->subDays($cooldownDays))
        ) {
            return false;
        }

        $receivable->loadMissing('client');
        $dueLabel = DisplayFormat::date($receivable->due_at) ?? 'data não informada';
        $balanceLabel = ReportLabels::money($receivable->balanceCents());

        $this->notifyPortalClient->execute(
            client: $receivable->client,
            subject: 'Cobrança vencida',
            message: "A cobrança \"{$receivable->description}\" venceu em {$dueLabel} e possui saldo em aberto de {$balanceLabel}. Acesse o portal para consultar os detalhes e instruções de pagamento.",
            actionUrl: route('client-portal.finance', ['receivable' => $receivable->id], absolute: true),
            type: PortalClientAlert::TYPE_FINANCE,
        );

        $receivable->update(['last_portal_reminder_at' => now()]);

        return true;
    }
}
