<?php

namespace App\Automations;

use App\Automations\Actions\CreateDocumentRequestAction;
use App\Automations\Actions\CreateTasksFromTemplateAction;
use App\Automations\Actions\NotifyOrganizationMembersAction;
use App\Models\AutomationLog;
use App\Models\AutomationRule;
use App\Models\Organization;
use App\Models\Plan;
use App\Support\Billing\PlanLimitChecker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class AutomationRunner
{
    public function __construct(
        private PlanLimitChecker $planLimitChecker,
        private CreateTasksFromTemplateAction $createTasksFromTemplateAction,
        private CreateDocumentRequestAction $createDocumentRequestAction,
        private NotifyOrganizationMembersAction $notifyOrganizationMembersAction,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function dispatch(
        Organization $organization,
        string $trigger,
        Model $subject,
        array $context = [],
        ?string $dedupeSuffix = null,
    ): void {
        if (! Plan::query()->exists() || ! $this->planLimitChecker->hasFeature($organization, 'automations')) {
            return;
        }

        $rules = AutomationRule::query()
            ->where('organization_id', $organization->id)
            ->where('trigger', $trigger)
            ->where('is_active', true)
            ->get();

        foreach ($rules as $rule) {
            if (! $this->matchesConditions($rule, $context)) {
                continue;
            }

            $this->runRule($rule, $trigger, $subject, $context, $dedupeSuffix);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function runRule(
        AutomationRule $rule,
        string $trigger,
        Model $subject,
        array $context = [],
        ?string $dedupeSuffix = null,
    ): ?AutomationLog {
        $dedupeKey = implode(':', array_filter([
            'rule',
            (string) $rule->id,
            $trigger,
            $subject->getMorphClass(),
            (string) $subject->getKey(),
            $dedupeSuffix,
        ]));

        try {
            $log = AutomationLog::query()->create([
                'organization_id' => $rule->organization_id,
                'automation_rule_id' => $rule->id,
                'trigger' => $trigger,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'dedupe_key' => $dedupeKey,
                'status' => AutomationLog::STATUS_RUNNING,
                'ran_at' => now(),
            ]);
        } catch (QueryException) {
            return null;
        }

        try {
            $results = [];

            foreach ($rule->actions ?? [] as $action) {
                $type = $action['type'] ?? null;
                $params = $action['params'] ?? [];

                $results[] = [
                    'type' => $type,
                    'result' => $this->executeAction($type, $rule, $subject, $params, $context),
                ];
            }

            $log->update([
                'status' => AutomationLog::STATUS_SUCCEEDED,
                'result' => $results,
                'ran_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('automation.rule_failed', [
                'rule_id' => $rule->id,
                'dedupe_key' => $dedupeKey,
                'message' => $exception->getMessage(),
            ]);

            $log->update([
                'status' => AutomationLog::STATUS_FAILED,
                'result' => ['error' => $exception->getMessage()],
                'ran_at' => now(),
            ]);
        }

        return $log->fresh();
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function executeAction(
        ?string $type,
        AutomationRule $rule,
        Model $subject,
        array $params,
        array $context,
    ): array {
        return match ($type) {
            AutomationRule::ACTION_CREATE_TASKS_FROM_TEMPLATE => $this->createTasksFromTemplateAction->execute($rule, $subject, $params, $context),
            AutomationRule::ACTION_CREATE_DOCUMENT_REQUEST => $this->createDocumentRequestAction->execute($rule, $subject, $params, $context),
            AutomationRule::ACTION_NOTIFY_ORGANIZATION_MEMBERS => $this->notifyOrganizationMembersAction->execute($rule, $subject, $params, $context),
            default => throw new \InvalidArgumentException("Ação de automação inválida: {$type}"),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function matchesConditions(AutomationRule $rule, array $context): bool
    {
        $conditions = $rule->conditions ?? [];

        if (isset($conditions['within_days'], $context['within_days'])
            && (int) $context['within_days'] > (int) $conditions['within_days']) {
            return false;
        }

        if (isset($conditions['stage'], $context['stage'])
            && $conditions['stage'] !== $context['stage']) {
            return false;
        }

        return true;
    }
}
