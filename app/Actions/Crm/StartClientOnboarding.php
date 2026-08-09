<?php

namespace App\Actions\Crm;

use App\Enums\TaskPriority;
use App\Models\Client;
use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateItem;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StartClientOnboarding
{
    /**
     * @return Collection<int, Task>
     */
    public function execute(
        Client $client,
        OnboardingTemplate $template,
        int $actorUserId,
        ?int $assignedMemberId = null,
    ): Collection {
        if ($template->organization_id !== $client->organization_id) {
            throw new InvalidArgumentException('Template de onboarding pertence a outra organização.');
        }

        if (! $template->is_active) {
            throw new InvalidArgumentException('Template de onboarding inativo.');
        }

        $template->loadMissing('items');

        if ($template->items->isEmpty()) {
            throw new InvalidArgumentException('Template de onboarding sem itens.');
        }

        return DB::transaction(function () use ($client, $template, $actorUserId, $assignedMemberId): Collection {
            $baseDate = now();

            return $template->items->map(function (OnboardingTemplateItem $item) use ($client, $actorUserId, $assignedMemberId, $baseDate): Task {
                return Task::query()->create([
                    'organization_id' => $client->organization_id,
                    'client_id' => $client->id,
                    'assigned_to_member_id' => $assignedMemberId,
                    'created_by_user_id' => $actorUserId,
                    'title' => '[Onboarding] '.$item->title,
                    'description' => $item->description,
                    'status' => Task::STATUS_PENDING,
                    'priority' => TaskPriority::Normal,
                    'due_at' => $baseDate->copy()->addDays($item->due_in_days)->toDateString(),
                ]);
            });
        });
    }
}
