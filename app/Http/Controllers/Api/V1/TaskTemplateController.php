<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\OrganizationMember;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class TaskTemplateController extends Controller
{
    public function index(Request $request, OrganizationContext $organizationContext): JsonResponse
    {
        $templates = TaskTemplate::query()
            ->with('items')
            ->whereBelongsTo($organizationContext->organization())
            ->when($request->boolean('active_only'), fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return response()->json($templates);
    }

    public function store(Request $request, OrganizationContext $organizationContext, RecordAuditLog $auditLog): JsonResponse
    {
        Gate::authorize('create', TaskTemplate::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'string', Rule::in([Task::PRIORITY_LOW, Task::PRIORITY_NORMAL, Task::PRIORITY_HIGH, Task::PRIORITY_CRITICAL])],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.due_in_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'items.*.priority' => ['nullable', 'string', Rule::in([Task::PRIORITY_LOW, Task::PRIORITY_NORMAL, Task::PRIORITY_HIGH, Task::PRIORITY_CRITICAL])],
            'items.*.checklist_items' => ['nullable', 'array'],
            'items.*.checklist_items.*.title' => ['required_with:items.*.checklist_items', 'string', 'max:255'],
            'items.*.checklist_items.*.is_required' => ['nullable', 'boolean'],
        ]);

        $template = DB::transaction(function () use ($data, $organizationContext): TaskTemplate {
            $template = TaskTemplate::create([
                'organization_id' => $organizationContext->id(),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'priority' => $data['priority'] ?? Task::PRIORITY_NORMAL,
                'is_active' => $data['is_active'] ?? true,
            ]);

            foreach ($data['items'] as $item) {
                $template->items()->create([
                    ...$item,
                    'organization_id' => $organizationContext->id(),
                    'priority' => $item['priority'] ?? $template->priority,
                    'due_in_days' => $item['due_in_days'] ?? 0,
                ]);
            }

            return $template;
        });

        $auditLog->execute('task_template.created', $request->user(), $organizationContext->organization(), $template, request: $request);

        return response()->json(['data' => $template->load('items')], Response::HTTP_CREATED);
    }

    public function createTasks(Request $request, TaskTemplate $template, RecordAuditLog $auditLog): JsonResponse
    {
        abort_if($template->organization_id !== app(OrganizationContext::class)->id(), Response::HTTP_NOT_FOUND);
        Gate::authorize('update', $template);

        $data = $request->validate([
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->where('organization_id', $template->organization_id)],
            'assigned_to_member_id' => ['required', 'integer', Rule::exists('organization_members', 'id')->where('organization_id', $template->organization_id)->where('status', OrganizationMember::STATUS_ACTIVE)],
            'base_date' => ['nullable', 'date'],
        ]);
        $this->assertAssigneeCanAccessClient($data['assigned_to_member_id'], $data['client_id'] ?? null);

        $baseDate = isset($data['base_date']) ? now()->parse($data['base_date']) : now();
        $tasks = DB::transaction(function () use ($template, $data, $baseDate, $request) {
            return $template->items->map(function ($item) use ($template, $data, $baseDate, $request) {
                $task = Task::create([
                    'organization_id' => $template->organization_id,
                    'client_id' => $data['client_id'] ?? null,
                    'assigned_to_member_id' => $data['assigned_to_member_id'],
                    'created_by_user_id' => $request->user()->id,
                    'task_template_id' => $template->id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'priority' => $item->priority,
                    'due_at' => $baseDate->copy()->addDays($item->due_in_days)->toDateString(),
                ]);

                foreach ($item->checklist_items ?? [] as $checklistItem) {
                    $task->checklistItems()->create([
                        'organization_id' => $template->organization_id,
                        'title' => $checklistItem['title'],
                        'is_required' => (bool) ($checklistItem['is_required'] ?? false),
                    ]);
                }

                return $task->load('checklistItems');
            });
        });

        $auditLog->execute('task_template.tasks_created', $request->user(), $template->organization, $template, ['tasks_count' => $tasks->count()], $request);

        return response()->json(['data' => $tasks], Response::HTTP_CREATED);
    }

    private function assertAssigneeCanAccessClient(int $memberId, ?int $clientId): void
    {
        if (! $clientId) {
            return;
        }

        $member = OrganizationMember::findOrFail($memberId);
        $client = Client::findOrFail($clientId);

        if ($client->access_policy === Client::ACCESS_ALL_MEMBERS) {
            return;
        }

        $hasAccess = $client->responsibles()->whereKey($member->id)->exists()
            || $client->accessMembers()->whereKey($member->id)->exists()
            || $member->isAdmin()
            || $member->isManager();

        abort_unless($hasAccess, Response::HTTP_UNPROCESSABLE_ENTITY, 'Assigned member does not have access to the client.');
    }
}
