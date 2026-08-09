<?php

namespace App\Console\Commands\Automations;

use App\Automations\AutomationRunner;
use App\Models\AutomationRule;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Receivable;
use App\Support\Billing\PlanLimitChecker;
use Illuminate\Console\Command;

class DispatchDueAutomationsCommand extends Command
{
    protected $signature = 'automations:dispatch-due';

    protected $description = 'Dispara automações temporais (documentos, contratos e cobranças).';

    public function handle(AutomationRunner $runner, PlanLimitChecker $planLimitChecker): int
    {
        $organizationIds = AutomationRule::query()
            ->where('is_active', true)
            ->whereIn('trigger', [
                AutomationRule::TRIGGER_DOCUMENT_EXPIRING,
                AutomationRule::TRIGGER_CONTRACT_EXPIRING,
                AutomationRule::TRIGGER_RECEIVABLE_OVERDUE,
            ])
            ->distinct()
            ->pluck('organization_id');

        foreach ($organizationIds as $organizationId) {
            $organization = Organization::query()->find($organizationId);

            if ($organization === null || ! $planLimitChecker->hasFeature($organization, 'automations')) {
                continue;
            }

            Document::query()
                ->where('organization_id', $organization->id)
                ->whereNotNull('expires_at')
                ->whereNotIn('status', [
                    Document::STATUS_REJECTED,
                    Document::STATUS_EXPIRED,
                    Document::STATUS_REPLACED,
                ])
                ->whereBetween('expires_at', [now()->toDateString(), now()->addDays(7)->toDateString()])
                ->limit(200)
                ->each(function (Document $document) use ($runner, $organization): void {
                    $runner->dispatch(
                        organization: $organization,
                        trigger: AutomationRule::TRIGGER_DOCUMENT_EXPIRING,
                        subject: $document,
                        context: [
                            'within_days' => now()->startOfDay()->diffInDays($document->expires_at),
                            'client_id' => $document->client_id,
                        ],
                        dedupeSuffix: $document->expires_at?->toDateString(),
                    );
                });

            Contract::query()
                ->where('organization_id', $organization->id)
                ->expiringWithinDays(30)
                ->limit(200)
                ->each(function (Contract $contract) use ($runner, $organization): void {
                    $runner->dispatch(
                        organization: $organization,
                        trigger: AutomationRule::TRIGGER_CONTRACT_EXPIRING,
                        subject: $contract,
                        context: [
                            'within_days' => now()->startOfDay()->diffInDays($contract->ends_at),
                            'client_id' => $contract->client_id,
                        ],
                        dedupeSuffix: $contract->ends_at?->toDateString(),
                    );
                });

            Receivable::query()
                ->where('organization_id', $organization->id)
                ->whereDate('due_at', '<', now()->toDateString())
                ->whereIn('status', [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL])
                ->limit(200)
                ->each(function (Receivable $receivable) use ($runner, $organization): void {
                    $runner->dispatch(
                        organization: $organization,
                        trigger: AutomationRule::TRIGGER_RECEIVABLE_OVERDUE,
                        subject: $receivable,
                        context: ['client_id' => $receivable->client_id],
                        dedupeSuffix: $receivable->due_at?->toDateString(),
                    );
                });
        }

        $this->info('Automações temporais despachadas.');

        return self::SUCCESS;
    }
}
