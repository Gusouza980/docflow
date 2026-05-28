<?php

namespace App\Http\Controllers\Web;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreTaskRequest;
use App\Http\Requests\Web\UpdateTaskRequest;
use App\Models\Client;
use App\Models\InternalReminder;
use App\Models\OrganizationMember;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Support\WebOrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class TaskController extends Controller
{
    public function index(Request $request, WebOrganizationContext $webOrganizationContext): Response|RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização para gerenciar tarefas.');
        }

        $tasks = $this->taskQuery($request, $membership)
            ->with(['client', 'assignee.user', 'checklistItems'])
            ->orderBy('due_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Tasks/Index', [
            'tasks' => [
                'data' => $tasks->getCollection()->map(fn (Task $task): array => $this->taskSummary($task)),
                'meta' => [
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage(),
                    'per_page' => $tasks->perPage(),
                    'total' => $tasks->total(),
                ],
            ],
            'filters' => [
                'client_id' => $request->string('client_id')->toString(),
                'assigned_to_member_id' => $request->string('assigned_to_member_id')->toString(),
                'status' => $request->string('status')->toString(),
                'priority' => $request->string('priority')->toString(),
                'flag' => $request->string('flag')->toString(),
            ],
            'options' => $this->options($membership),
            'can' => [
                'create' => $request->user()->can('create', Task::class) && $membership->role !== OrganizationMember::ROLE_READONLY,
            ],
        ]);
    }

    public function store(StoreTaskRequest $request, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($membership->role === OrganizationMember::ROLE_READONLY, HttpResponse::HTTP_FORBIDDEN);
        Gate::authorize('create', Task::class);

        $data = $request->validated();
        $this->assertAssigneeCanAccessClient($data['assigned_to_member_id'], $data['client_id'] ?? null);

        $task = Task::create([
            ...$data,
            'organization_id' => $membership->organization_id,
            'created_by_user_id' => $request->user()->id,
        ]);

        $this->createReminder($task, $task->assignee->user_id);
        $auditLog->execute('web.task.created', $request->user(), $membership->organization, $task, request: $request);

        return redirect()->route('tasks.show', $task)->with('status', 'Tarefa criada.');
    }

    public function show(Task $task, Request $request, WebOrganizationContext $webOrganizationContext): Response
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $this->authorizeTask('view', $task, $membership);

        $task->load(['client', 'assignee.user', 'checklistItems']);

        return Inertia::render('Tasks/Show', [
            'task' => $this->taskDetail($task),
            'options' => $this->options($membership),
            'can' => [
                'update' => $request->user()->can('update', $task),
            ],
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $this->authorizeTask('update', $task, $membership);

        $data = $request->validated();
        $this->assertAssigneeCanAccessClient($data['assigned_to_member_id'], $data['client_id'] ?? null);

        $task->update($data);
        $auditLog->execute('web.task.updated', $request->user(), $task->organization, $task, request: $request);

        return redirect()->route('tasks.show', $task)->with('status', 'Tarefa atualizada.');
    }

    public function updateStatus(Task $task, Request $request, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $this->authorizeTask('update', $task, $membership);

        $data = $request->validate([
            'status' => ['required', Rule::in([Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS, Task::STATUS_BLOCKED, Task::STATUS_CANCELLED])],
        ]);

        $task->update([
            'status' => $data['status'],
            'started_at' => $data['status'] === Task::STATUS_IN_PROGRESS ? ($task->started_at ?? now()) : $task->started_at,
        ]);
        $auditLog->execute('web.task.status_updated', $request->user(), $task->organization, $task, request: $request);

        return redirect()->route('tasks.show', $task)->with('status', 'Status atualizado.');
    }

    public function complete(Task $task, Request $request, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $this->authorizeTask('update', $task, $membership);

        $incompleteRequired = $task->checklistItems()->where('is_required', true)->where('is_completed', false)->exists();

        if ($incompleteRequired) {
            return redirect()->route('tasks.show', $task)->with('error', 'Conclua os itens obrigatórios do checklist antes de finalizar.');
        }

        $data = $request->validate(['completion_notes' => ['nullable', 'string']]);
        $task->update([
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => now(),
            'completion_notes' => $data['completion_notes'] ?? null,
        ]);
        $auditLog->execute('web.task.completed', $request->user(), $task->organization, $task, request: $request);

        return redirect()->route('tasks.show', $task)->with('status', 'Tarefa concluída.');
    }

    private function taskQuery(Request $request, OrganizationMember $membership): Builder
    {
        return Task::query()
            ->whereBelongsTo($membership->organization)
            ->when($request->integer('client_id'), fn (Builder $query, int $clientId) => $query->where('client_id', $clientId))
            ->when($request->integer('assigned_to_member_id'), fn (Builder $query, int $memberId) => $query->where('assigned_to_member_id', $memberId))
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->string('priority')->toString(), fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($request->string('flag')->toString() === 'overdue', fn (Builder $query) => $query->whereDate('due_at', '<', now()->toDateString())->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED]))
            ->when($request->string('flag')->toString() === 'critical', fn (Builder $query) => $query->where('priority', Task::PRIORITY_CRITICAL))
            ->when(! $membership->isAdmin() && ! $membership->isManager(), function (Builder $query) use ($membership): void {
                $query->where(function (Builder $query) use ($membership): void {
                    $query->whereNull('client_id')
                        ->orWhereHas('client', function (Builder $query) use ($membership): void {
                            $query->where('access_policy', Client::ACCESS_ALL_MEMBERS)
                                ->orWhereHas('responsibles', fn (Builder $query) => $query->whereKey($membership->id))
                                ->orWhereHas('accessMembers', fn (Builder $query) => $query->whereKey($membership->id));
                        });
                });
            });
    }

    private function taskSummary(Task $task): array
    {
        $totalChecklist = $task->checklistItems->count();
        $completedChecklist = $task->checklistItems->where('is_completed', true)->count();

        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'priority' => $task->priority,
            'due_at' => $task->due_at?->toDateString(),
            'is_overdue' => $task->isOverdue(),
            'client' => $task->client ? ['id' => $task->client->id, 'name' => $task->client->display_name] : null,
            'assignee' => $task->assignee ? ['id' => $task->assignee->id, 'name' => $task->assignee->user?->name] : null,
            'checklist_progress' => "{$completedChecklist}/{$totalChecklist}",
            'href' => route('tasks.show', $task, absolute: false),
        ];
    }

    private function taskDetail(Task $task): array
    {
        return [
            ...$this->taskSummary($task),
            'completion_notes' => $task->completion_notes,
            'checklist_items' => $task->checklistItems->map(fn (TaskChecklistItem $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'is_required' => $item->is_required,
                'is_completed' => $item->is_completed,
            ])->values(),
        ];
    }

    private function options(OrganizationMember $membership): array
    {
        return [
            'clients' => Client::query()
                ->whereBelongsTo($membership->organization)
                ->orderBy('display_name')
                ->get(['id', 'display_name'])
                ->map(fn (Client $client): array => ['value' => $client->id, 'label' => $client->display_name])
                ->values(),
            'members' => OrganizationMember::query()
                ->with('user')
                ->whereBelongsTo($membership->organization)
                ->where('status', OrganizationMember::STATUS_ACTIVE)
                ->get()
                ->map(fn (OrganizationMember $member): array => ['value' => $member->id, 'label' => $member->user?->name ?? "Membro #{$member->id}"])
                ->values(),
        ];
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

        abort_unless($hasAccess, HttpResponse::HTTP_UNPROCESSABLE_ENTITY, 'Responsável não tem acesso ao cliente.');
    }

    private function createReminder(Task $task, int $userId): void
    {
        InternalReminder::firstOrCreate([
            'organization_id' => $task->organization_id,
            'user_id' => $userId,
            'remindable_type' => $task->getMorphClass(),
            'remindable_id' => $task->id,
            'type' => 'task_assigned',
        ], ['remind_at' => $task->due_at->copy()->subDay()]);
    }

    private function authorizeTask(string $ability, Task $task, OrganizationMember $membership): void
    {
        abort_if($task->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize($ability, $task);
    }
}
