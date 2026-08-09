<?php

namespace App\Automations\Actions;

use App\Models\AutomationRule;
use App\Models\Client;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateTasksFromTemplateAction
{
    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function execute(AutomationRule $rule, Model $subject, array $params, array $context = []): array
    {
        $templateId = $params['task_template_id'] ?? null;

        if (! $templateId) {
            throw new InvalidArgumentException('task_template_id é obrigatório na ação create_tasks_from_template.');
        }

        $template = TaskTemplate::query()
            ->with('items')
            ->whereKey($templateId)
            ->where('organization_id', $rule->organization_id)
            ->where('is_active', true)
            ->firstOrFail();

        $clientId = $subject instanceof Client
            ? $subject->id
            : ($context['client_id'] ?? null);

        $assignedMemberId = $params['assigned_to_member_id']
            ?? $context['assigned_to_member_id']
            ?? ($subject instanceof Client ? $subject->primary_responsible_member_id : null);

        $tasks = DB::transaction(function () use ($template, $clientId, $assignedMemberId): array {
            $baseDate = now();

            return $template->items->map(function (TaskTemplateItem $item) use ($template, $clientId, $assignedMemberId, $baseDate): int {
                $task = Task::query()->create([
                    'organization_id' => $template->organization_id,
                    'client_id' => $clientId,
                    'assigned_to_member_id' => $assignedMemberId,
                    'created_by_user_id' => null,
                    'task_template_id' => $template->id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'priority' => $item->priority,
                    'due_at' => $baseDate->copy()->addDays($item->due_in_days)->toDateString(),
                ]);

                return $task->id;
            })->all();
        });

        return ['task_ids' => $tasks, 'count' => count($tasks)];
    }
}
