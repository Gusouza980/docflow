<?php

namespace App\Http\Controllers\Web;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreDeadlineRequest;
use App\Http\Requests\Web\UpdateDeadlineRequest;
use App\Models\Client;
use App\Models\Deadline;
use App\Models\InternalReminder;
use App\Models\OrganizationMember;
use App\Support\WebOrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DeadlineController extends Controller
{
    public function index(Request $request, WebOrganizationContext $webOrganizationContext): Response|RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização para gerenciar prazos.');
        }

        $deadlines = $this->deadlineQuery($request, $membership)
            ->with(['client', 'assignee.user'])
            ->orderBy('due_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Deadlines/Index', [
            'deadlines' => [
                'data' => $deadlines->getCollection()->map(fn (Deadline $deadline): array => $this->deadlineSummary($deadline)),
                'meta' => [
                    'current_page' => $deadlines->currentPage(),
                    'last_page' => $deadlines->lastPage(),
                    'per_page' => $deadlines->perPage(),
                    'total' => $deadlines->total(),
                ],
            ],
            'filters' => [
                'client_id' => $request->string('client_id')->toString(),
                'assigned_to_member_id' => $request->string('assigned_to_member_id')->toString(),
                'status' => $request->string('status')->toString(),
                'overdue' => $request->boolean('overdue'),
            ],
            'options' => $this->options($membership),
            'can' => [
                'create' => $request->user()->can('create', Deadline::class) && $membership->role !== OrganizationMember::ROLE_READONLY,
            ],
        ]);
    }

    public function store(StoreDeadlineRequest $request, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($membership->role === OrganizationMember::ROLE_READONLY, HttpResponse::HTTP_FORBIDDEN);
        Gate::authorize('create', Deadline::class);

        $deadline = Deadline::create([
            ...$request->validated(),
            'organization_id' => $membership->organization_id,
            'created_by_user_id' => $request->user()->id,
        ]);
        $this->createReminder($deadline);
        $auditLog->execute('web.deadline.created', $request->user(), $membership->organization, $deadline, request: $request);

        return redirect()->route('deadlines.index')->with('status', 'Prazo criado.');
    }

    public function update(UpdateDeadlineRequest $request, Deadline $deadline, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $this->authorizeDeadline('update', $deadline, $membership);

        $deadline->update($request->validated());
        $auditLog->execute('web.deadline.updated', $request->user(), $deadline->organization, $deadline, request: $request);

        return redirect()->route('deadlines.index')->with('status', 'Prazo atualizado.');
    }

    public function requestReview(Deadline $deadline, Request $request, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $this->authorizeDeadline('update', $deadline, $membership);

        $data = $request->validate(['review_notes' => ['nullable', 'string']]);
        $deadline->update([
            'status' => Deadline::STATUS_REVIEW_REQUESTED,
            'review_requested_at' => now(),
            'review_notes' => $data['review_notes'] ?? null,
        ]);
        $auditLog->execute('web.deadline.review_requested', $request->user(), $deadline->organization, $deadline, request: $request);

        return redirect()->route('deadlines.index')->with('status', 'Revisão solicitada.');
    }

    public function approveReview(Deadline $deadline, Request $request, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $this->authorizeDeadline('update', $deadline, $membership);

        $deadline->update([
            'status' => Deadline::STATUS_REVIEW_APPROVED,
            'review_approved_at' => now(),
        ]);
        $auditLog->execute('web.deadline.review_approved', $request->user(), $deadline->organization, $deadline, request: $request);

        return redirect()->route('deadlines.index')->with('status', 'Revisão aprovada.');
    }

    public function complete(Deadline $deadline, Request $request, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $this->authorizeDeadline('update', $deadline, $membership);

        if ($deadline->requires_review && $deadline->status !== Deadline::STATUS_REVIEW_APPROVED) {
            return redirect()->route('deadlines.index')->with('error', 'Este prazo exige revisão aprovada antes da conclusão.');
        }

        $data = $request->validate(['completion_notes' => ['nullable', 'string']]);
        $deadline->update([
            'status' => Deadline::STATUS_COMPLETED,
            'completed_at' => now(),
            'completion_notes' => $data['completion_notes'] ?? null,
        ]);
        $auditLog->execute('web.deadline.completed', $request->user(), $deadline->organization, $deadline, request: $request);

        return redirect()->route('deadlines.index')->with('status', 'Prazo concluído.');
    }

    private function deadlineQuery(Request $request, OrganizationMember $membership): Builder
    {
        return Deadline::query()
            ->whereBelongsTo($membership->organization)
            ->when($request->integer('client_id'), fn (Builder $query, int $clientId) => $query->where('client_id', $clientId))
            ->when($request->integer('assigned_to_member_id'), fn (Builder $query, int $memberId) => $query->where('assigned_to_member_id', $memberId))
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->boolean('overdue'), fn (Builder $query) => $query->whereDate('due_at', '<', now()->toDateString())->where('status', '!=', Deadline::STATUS_COMPLETED))
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

    private function deadlineSummary(Deadline $deadline): array
    {
        return [
            'id' => $deadline->id,
            'title' => $deadline->title,
            'description' => $deadline->description,
            'type' => $deadline->type,
            'urgency' => $deadline->urgency,
            'status' => $deadline->status,
            'due_at' => $deadline->due_at?->toDateString(),
            'requires_review' => $deadline->requires_review,
            'review_notes' => $deadline->review_notes,
            'client' => $deadline->client ? ['id' => $deadline->client->id, 'name' => $deadline->client->display_name] : null,
            'assignee' => $deadline->assignee ? ['id' => $deadline->assignee->id, 'name' => $deadline->assignee->user?->name] : null,
        ];
    }

    private function options(OrganizationMember $membership): array
    {
        return [
            'clients' => Client::query()->whereBelongsTo($membership->organization)->orderBy('display_name')->get(['id', 'display_name'])->map(fn (Client $client): array => ['value' => $client->id, 'label' => $client->display_name])->values(),
            'members' => OrganizationMember::query()->with('user')->whereBelongsTo($membership->organization)->where('status', OrganizationMember::STATUS_ACTIVE)->get()->map(fn (OrganizationMember $member): array => ['value' => $member->id, 'label' => $member->user?->name ?? "Membro #{$member->id}"])->values(),
        ];
    }

    private function createReminder(Deadline $deadline): void
    {
        InternalReminder::firstOrCreate([
            'organization_id' => $deadline->organization_id,
            'user_id' => $deadline->assignee->user_id,
            'remindable_type' => $deadline->getMorphClass(),
            'remindable_id' => $deadline->id,
            'type' => 'deadline_due_soon',
        ], ['remind_at' => $deadline->due_at->copy()->subDay()]);
    }

    private function authorizeDeadline(string $ability, Deadline $deadline, OrganizationMember $membership): void
    {
        abort_if($deadline->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize($ability, $deadline);
    }
}
