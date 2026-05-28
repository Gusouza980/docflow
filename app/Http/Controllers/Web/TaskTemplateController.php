<?php

namespace App\Http\Controllers\Web;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CreateTasksFromTemplateRequest;
use App\Http\Requests\Web\StoreTaskTemplateRequest;
use App\Models\Client;
use App\Models\OrganizationMember;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class TaskTemplateController extends Controller
{
    public function index(Request $request, WebOrganizationContext $webOrganizationContext): Response|RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização para gerenciar modelos.');
        }

        $templates = TaskTemplate::query()
            ->with('items')
            ->whereBelongsTo($membership->organization)
            ->when($request->boolean('active_only'), fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get();

        return Inertia::render('TaskTemplates/Index', [
            'templates' => $templates->map(fn (TaskTemplate $template): array => $this->templateSummary($template))->values(),
            'options' => $this->options($membership),
            'can' => [
                'create' => $request->user()->can('create', TaskTemplate::class) && $membership->role !== OrganizationMember::ROLE_READONLY,
                'update' => $membership->role !== OrganizationMember::ROLE_READONLY,
            ],
        ]);
    }

    public function store(StoreTaskTemplateRequest $request, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($membership->role === OrganizationMember::ROLE_READONLY, HttpResponse::HTTP_FORBIDDEN);
        Gate::authorize('create', TaskTemplate::class);

        $template = DB::transaction(function () use ($request, $membership): TaskTemplate {
            $data = $request->validated();
            $template = TaskTemplate::create([
                'organization_id' => $membership->organization_id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'priority' => $data['priority'] ?? Task::PRIORITY_NORMAL,
                'is_active' => $data['is_active'] ?? true,
            ]);

            foreach ($data['items'] as $item) {
                $template->items()->create([
                    ...$item,
                    'organization_id' => $membership->organization_id,
                    'priority' => $item['priority'] ?? $template->priority,
                    'due_in_days' => $item['due_in_days'] ?? 0,
                ]);
            }

            return $template;
        });

        $auditLog->execute('web.task_template.created', $request->user(), $membership->organization, $template, request: $request);

        return redirect()->route('task-templates.index')->with('status', 'Modelo criado.');
    }

    public function update(StoreTaskTemplateRequest $request, TaskTemplate $template, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($template->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $template);

        DB::transaction(function () use ($request, $template): void {
            $data = $request->validated();
            $template->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'priority' => $data['priority'] ?? Task::PRIORITY_NORMAL,
                'is_active' => $data['is_active'] ?? false,
            ]);

            $template->items()->delete();

            foreach ($data['items'] as $item) {
                $template->items()->create([
                    ...$item,
                    'organization_id' => $template->organization_id,
                    'priority' => $item['priority'] ?? $template->priority,
                    'due_in_days' => $item['due_in_days'] ?? 0,
                ]);
            }
        });

        $auditLog->execute('web.task_template.updated', $request->user(), $template->organization, $template, request: $request);

        return redirect()->route('task-templates.index')->with('status', 'Modelo atualizado.');
    }

    public function createTasks(CreateTasksFromTemplateRequest $request, TaskTemplate $template, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($template->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $template);

        $data = $request->validated();
        $baseDate = isset($data['base_date']) ? now()->parse($data['base_date']) : now();

        $tasks = DB::transaction(function () use ($template, $data, $baseDate, $request) {
            return $template->items->map(function (TaskTemplateItem $item) use ($template, $data, $baseDate, $request): Task {
                return Task::create([
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
            });
        });

        $auditLog->execute('web.task_template.tasks_created', $request->user(), $template->organization, $template, ['tasks_count' => $tasks->count()], $request);

        return redirect()->route('tasks.index')->with('status', "{$tasks->count()} tarefas criadas pelo modelo.");
    }

    private function templateSummary(TaskTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'priority' => $template->priority,
            'is_active' => $template->is_active,
            'items' => $template->items->map(fn (TaskTemplateItem $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'priority' => $item->priority,
                'due_in_days' => $item->due_in_days,
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
}
