<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\InternalReminder;
use App\Models\OrganizationMember;
use App\Models\Task;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class TaskController extends Controller
{
    public function index(Request $request, OrganizationContext $organizationContext): JsonResponse
    {
        $membership = $organizationContext->membership();

        $tasks = Task::query()
            ->with(['client', 'assignee.user', 'checklistItems'])
            ->whereBelongsTo($organizationContext->organization())
            ->when($request->integer('client_id'), fn ($query, int $clientId) => $query->where('client_id', $clientId))
            ->when($request->integer('assigned_to_member_id'), fn ($query, int $memberId) => $query->where('assigned_to_member_id', $memberId))
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->string('priority')->toString(), fn ($query, string $priority) => $query->where('priority', $priority))
            ->when($request->boolean('overdue'), fn ($query) => $query->whereDate('due_at', '<', now()->toDateString())->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED]))
            ->when($request->boolean('critical'), fn ($query) => $query->where('priority', Task::PRIORITY_CRITICAL))
            ->when(! $membership?->isAdmin() && ! $membership?->isManager(), function ($query) use ($membership): void {
                $query->where(function ($query) use ($membership): void {
                    $query->whereNull('client_id')
                        ->orWhereHas('client', function ($query) use ($membership): void {
                            $query->where('access_policy', Client::ACCESS_ALL_MEMBERS)
                                ->orWhereHas('responsibles', fn ($query) => $query->whereKey($membership->id))
                                ->orWhereHas('accessMembers', fn ($query) => $query->whereKey($membership->id));
                        });
                });
            })
            ->orderBy('due_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($tasks);
    }

    public function store(Request $request, OrganizationContext $organizationContext, RecordAuditLog $auditLog): JsonResponse
    {
        Gate::authorize('create', Task::class);
        $data = $this->validatedTaskData($request, $organizationContext);
        $this->assertAssigneeCanAccessClient($data['assigned_to_member_id'], $data['client_id'] ?? null);

        $task = Task::create([
            ...$data,
            'organization_id' => $organizationContext->id(),
            'created_by_user_id' => $request->user()->id,
        ]);
        $this->createReminder($task, 'task_assigned', $task->assignee->user_id, $organizationContext->id(), $task->due_at->copy()->subDay());
        $auditLog->execute('task.created', $request->user(), $organizationContext->organization(), $task, request: $request);

        return response()->json(['data' => $task->load(['client', 'assignee.user', 'checklistItems'])], Response::HTTP_CREATED);
    }

    public function show(Task $task): JsonResponse
    {
        $this->authorizeTask('view', $task);

        return response()->json(['data' => $task->load(['client', 'assignee.user', 'checklistItems'])]);
    }

    public function update(Request $request, Task $task, RecordAuditLog $auditLog): JsonResponse
    {
        $this->authorizeTask('update', $task);
        $data = $this->validatedTaskData($request, app(OrganizationContext::class), true);
        $this->assertAssigneeCanAccessClient($data['assigned_to_member_id'] ?? $task->assigned_to_member_id, $data['client_id'] ?? $task->client_id);

        $task->update($data);
        $auditLog->execute('task.updated', $request->user(), $task->organization, $task, request: $request);

        return response()->json(['data' => $task->load(['client', 'assignee.user', 'checklistItems'])]);
    }

    public function updateStatus(Request $request, Task $task, RecordAuditLog $auditLog): JsonResponse
    {
        $this->authorizeTask('update', $task);
        $data = $request->validate([
            'status' => ['required', Rule::in([Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS, Task::STATUS_BLOCKED, Task::STATUS_CANCELLED])],
        ]);

        $task->update([
            'status' => $data['status'],
            'started_at' => $data['status'] === Task::STATUS_IN_PROGRESS ? ($task->started_at ?? now()) : $task->started_at,
        ]);
        $auditLog->execute('task.status_updated', $request->user(), $task->organization, $task, request: $request);

        return response()->json(['data' => $task->load(['client', 'assignee.user', 'checklistItems'])]);
    }

    public function assign(Request $request, Task $task, RecordAuditLog $auditLog): JsonResponse
    {
        $this->authorizeTask('update', $task);
        $data = $request->validate([
            'assigned_to_member_id' => ['required', 'integer', Rule::exists('organization_members', 'id')->where('organization_id', $task->organization_id)->where('status', OrganizationMember::STATUS_ACTIVE)],
        ]);
        $this->assertAssigneeCanAccessClient($data['assigned_to_member_id'], $task->client_id);

        $task->update(['assigned_to_member_id' => $data['assigned_to_member_id']]);
        $this->createReminder($task, 'task_assigned', $task->assignee->user_id, $task->organization_id, $task->due_at->copy()->subDay());
        $auditLog->execute('task.assigned', $request->user(), $task->organization, $task, request: $request);

        return response()->json(['data' => $task->load(['client', 'assignee.user', 'checklistItems'])]);
    }

    public function complete(Request $request, Task $task, RecordAuditLog $auditLog): JsonResponse
    {
        $this->authorizeTask('update', $task);
        $incompleteRequired = $task->checklistItems()
            ->where('is_required', true)
            ->where('is_completed', false)
            ->exists();

        if ($incompleteRequired) {
            return response()->json(['message' => 'Required checklist items must be completed before completing the task.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validate(['completion_notes' => ['nullable', 'string']]);
        $task->update([
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => now(),
            'completion_notes' => $data['completion_notes'] ?? null,
        ]);
        $auditLog->execute('task.completed', $request->user(), $task->organization, $task, request: $request);

        return response()->json(['data' => $task->load(['client', 'assignee.user', 'checklistItems'])]);
    }

    private function validatedTaskData(Request $request, OrganizationContext $organizationContext, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'client_id' => ['sometimes', 'nullable', 'integer', Rule::exists('clients', 'id')->where('organization_id', $organizationContext->id())],
            'assigned_to_member_id' => [$required, 'integer', Rule::exists('organization_members', 'id')->where('organization_id', $organizationContext->id())->where('status', OrganizationMember::STATUS_ACTIVE)],
            'title' => [$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priority' => ['sometimes', 'string', Rule::in([Task::PRIORITY_LOW, Task::PRIORITY_NORMAL, Task::PRIORITY_HIGH, Task::PRIORITY_CRITICAL])],
            'due_at' => [$required, 'date'],
        ]);
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

    private function createReminder(Task $task, string $type, int $userId, int $organizationId, mixed $remindAt): void
    {
        InternalReminder::firstOrCreate([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'remindable_type' => $task->getMorphClass(),
            'remindable_id' => $task->id,
            'type' => $type,
        ], ['remind_at' => $remindAt]);
    }

    private function authorizeTask(string $ability, Task $task): void
    {
        abort_if($task->organization_id !== app(OrganizationContext::class)->id(), Response::HTTP_NOT_FOUND);
        Gate::authorize($ability, $task);
    }
}
