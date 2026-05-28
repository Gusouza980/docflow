<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Deadline;
use App\Models\InternalReminder;
use App\Models\OrganizationMember;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class DeadlineController extends Controller
{
    public function index(Request $request, OrganizationContext $organizationContext): JsonResponse
    {
        $membership = $organizationContext->membership();

        $deadlines = Deadline::query()
            ->with(['client', 'assignee.user'])
            ->whereBelongsTo($organizationContext->organization())
            ->when($request->integer('client_id'), fn ($query, int $clientId) => $query->where('client_id', $clientId))
            ->when($request->integer('assigned_to_member_id'), fn ($query, int $memberId) => $query->where('assigned_to_member_id', $memberId))
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->boolean('overdue'), fn ($query) => $query->whereDate('due_at', '<', now()->toDateString())->where('status', '!=', Deadline::STATUS_COMPLETED))
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

        return response()->json($deadlines);
    }

    public function store(Request $request, OrganizationContext $organizationContext, RecordAuditLog $auditLog): JsonResponse
    {
        Gate::authorize('create', Deadline::class);
        $data = $this->validatedDeadlineData($request, $organizationContext);

        $deadline = Deadline::create([
            ...$data,
            'organization_id' => $organizationContext->id(),
            'created_by_user_id' => $request->user()->id,
        ]);
        $this->createReminder($deadline, $deadline->assignee->user_id, $organizationContext->id());
        $auditLog->execute('deadline.created', $request->user(), $organizationContext->organization(), $deadline, request: $request);

        return response()->json(['data' => $deadline->load(['client', 'assignee.user'])], Response::HTTP_CREATED);
    }

    public function update(Request $request, Deadline $deadline, RecordAuditLog $auditLog): JsonResponse
    {
        $this->authorizeDeadline('update', $deadline);
        $data = $this->validatedDeadlineData($request, app(OrganizationContext::class), true);

        $deadline->update($data);
        $auditLog->execute('deadline.updated', $request->user(), $deadline->organization, $deadline, request: $request);

        return response()->json(['data' => $deadline->load(['client', 'assignee.user'])]);
    }

    public function complete(Request $request, Deadline $deadline, RecordAuditLog $auditLog): JsonResponse
    {
        $this->authorizeDeadline('update', $deadline);

        if ($deadline->requires_review && $deadline->status !== Deadline::STATUS_REVIEW_APPROVED) {
            return response()->json(['message' => 'Deadline requires approved review before completion.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validate(['completion_notes' => ['nullable', 'string']]);
        $deadline->update([
            'status' => Deadline::STATUS_COMPLETED,
            'completed_at' => now(),
            'completion_notes' => $data['completion_notes'] ?? null,
        ]);
        $auditLog->execute('deadline.completed', $request->user(), $deadline->organization, $deadline, request: $request);

        return response()->json(['data' => $deadline->load(['client', 'assignee.user'])]);
    }

    public function requestReview(Request $request, Deadline $deadline, RecordAuditLog $auditLog): JsonResponse
    {
        $this->authorizeDeadline('update', $deadline);
        $data = $request->validate(['review_notes' => ['nullable', 'string']]);
        $deadline->update([
            'status' => Deadline::STATUS_REVIEW_REQUESTED,
            'review_requested_at' => now(),
            'review_notes' => $data['review_notes'] ?? null,
        ]);
        $auditLog->execute('deadline.review_requested', $request->user(), $deadline->organization, $deadline, request: $request);

        return response()->json(['data' => $deadline->load(['client', 'assignee.user'])]);
    }

    public function approveReview(Request $request, Deadline $deadline, RecordAuditLog $auditLog): JsonResponse
    {
        $this->authorizeDeadline('update', $deadline);
        $deadline->update([
            'status' => Deadline::STATUS_REVIEW_APPROVED,
            'review_approved_at' => now(),
        ]);
        $auditLog->execute('deadline.review_approved', $request->user(), $deadline->organization, $deadline, request: $request);

        return response()->json(['data' => $deadline->load(['client', 'assignee.user'])]);
    }

    private function validatedDeadlineData(Request $request, OrganizationContext $organizationContext, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'client_id' => ['sometimes', 'nullable', 'integer', Rule::exists('clients', 'id')->where('organization_id', $organizationContext->id())],
            'assigned_to_member_id' => [$required, 'integer', Rule::exists('organization_members', 'id')->where('organization_id', $organizationContext->id())->where('status', OrganizationMember::STATUS_ACTIVE)],
            'title' => [$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', 'string', 'max:64'],
            'urgency' => ['sometimes', 'string', Rule::in([Deadline::URGENCY_LOW, Deadline::URGENCY_NORMAL, Deadline::URGENCY_HIGH, Deadline::URGENCY_CRITICAL])],
            'due_at' => [$required, 'date'],
            'requires_review' => ['sometimes', 'boolean'],
        ]);
    }

    private function createReminder(Deadline $deadline, int $userId, int $organizationId): void
    {
        InternalReminder::firstOrCreate([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'remindable_type' => $deadline->getMorphClass(),
            'remindable_id' => $deadline->id,
            'type' => 'deadline_due_soon',
        ], ['remind_at' => $deadline->due_at->copy()->subDay()]);
    }

    private function authorizeDeadline(string $ability, Deadline $deadline): void
    {
        abort_if($deadline->organization_id !== app(OrganizationContext::class)->id(), Response::HTTP_NOT_FOUND);
        Gate::authorize($ability, $deadline);
    }
}
