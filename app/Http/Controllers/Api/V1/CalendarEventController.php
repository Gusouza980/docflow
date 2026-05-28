<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\InternalReminder;
use App\Models\OrganizationMember;
use App\Models\Task;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class CalendarEventController extends Controller
{
    public function index(Request $request, OrganizationContext $organizationContext): JsonResponse
    {
        $membership = $organizationContext->membership();

        $events = CalendarEvent::query()
            ->with(['client', 'participants.member.user'])
            ->whereBelongsTo($organizationContext->organization())
            ->when($request->date('from'), fn ($query, $from) => $query->where('starts_at', '>=', $from))
            ->when($request->date('to'), fn ($query, $to) => $query->where('starts_at', '<=', $to))
            ->when($request->string('type')->toString(), fn ($query, string $type) => $query->where('type', $type))
            ->when($request->integer('client_id'), fn ($query, int $clientId) => $query->where('client_id', $clientId))
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
            ->orderBy('starts_at')
            ->paginate($request->integer('per_page', 50));

        return response()->json($events);
    }

    public function store(Request $request, OrganizationContext $organizationContext, RecordAuditLog $auditLog): JsonResponse
    {
        Gate::authorize('create', CalendarEvent::class);
        $data = $this->validatedEventData($request, $organizationContext);

        $event = DB::transaction(function () use ($data, $request, $organizationContext): CalendarEvent {
            $event = CalendarEvent::create([
                ...collect($data)->except(['participants', 'tasks'])->all(),
                'organization_id' => $organizationContext->id(),
                'created_by_user_id' => $request->user()->id,
            ]);

            foreach ($data['participants'] ?? [] as $participant) {
                $event->participants()->create([
                    ...$participant,
                    'organization_id' => $organizationContext->id(),
                ]);
            }

            foreach ($event->participants()->whereNotNull('organization_member_id')->with('member')->get() as $participant) {
                $this->createReminder($event, $participant->member->user_id, $organizationContext->id());
            }

            return $event;
        });

        $auditLog->execute('calendar_event.created', $request->user(), $organizationContext->organization(), $event, request: $request);

        return response()->json(['data' => $event->load(['client', 'participants.member.user'])], Response::HTTP_CREATED);
    }

    public function update(Request $request, CalendarEvent $event, RecordAuditLog $auditLog): JsonResponse
    {
        $this->authorizeEvent('update', $event);
        $data = $this->validatedEventData($request, app(OrganizationContext::class), true);

        DB::transaction(function () use ($event, $data): void {
            $event->update(collect($data)->except(['participants', 'tasks'])->all());

            if (array_key_exists('participants', $data)) {
                $event->participants()->delete();
                foreach ($data['participants'] ?? [] as $participant) {
                    $event->participants()->create([
                        ...$participant,
                        'organization_id' => $event->organization_id,
                    ]);
                }
            }
        });

        $auditLog->execute('calendar_event.updated', $request->user(), $event->organization, $event, request: $request);

        return response()->json(['data' => $event->load(['client', 'participants.member.user'])]);
    }

    public function notes(Request $request, CalendarEvent $event, RecordAuditLog $auditLog): JsonResponse
    {
        $this->authorizeEvent('update', $event);
        $data = $request->validate([
            'notes' => ['required', 'string'],
            'tasks' => ['nullable', 'array'],
            'tasks.*.title' => ['required_with:tasks', 'string', 'max:255'],
            'tasks.*.assigned_to_member_id' => ['required_with:tasks', 'integer', Rule::exists('organization_members', 'id')->where('organization_id', $event->organization_id)->where('status', OrganizationMember::STATUS_ACTIVE)],
            'tasks.*.due_at' => ['required_with:tasks', 'date'],
            'tasks.*.priority' => ['nullable', Rule::in([Task::PRIORITY_LOW, Task::PRIORITY_NORMAL, Task::PRIORITY_HIGH, Task::PRIORITY_CRITICAL])],
        ]);

        DB::transaction(function () use ($event, $data, $request): void {
            $event->update([
                'notes' => $data['notes'],
                'notes_recorded_at' => now(),
                'status' => CalendarEvent::STATUS_DONE,
            ]);

            foreach ($data['tasks'] ?? [] as $task) {
                Task::create([
                    'organization_id' => $event->organization_id,
                    'client_id' => $event->client_id,
                    'assigned_to_member_id' => $task['assigned_to_member_id'],
                    'created_by_user_id' => $request->user()->id,
                    'title' => $task['title'],
                    'description' => "Criada a partir da reuniao: {$event->title}",
                    'priority' => $task['priority'] ?? Task::PRIORITY_NORMAL,
                    'due_at' => $task['due_at'],
                ]);
            }
        });

        $auditLog->execute('calendar_event.notes_recorded', $request->user(), $event->organization, $event, request: $request);

        return response()->json(['data' => $event->load(['client', 'participants.member.user'])]);
    }

    private function validatedEventData(Request $request, OrganizationContext $organizationContext, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'client_id' => ['sometimes', 'nullable', 'integer', Rule::exists('clients', 'id')->where('organization_id', $organizationContext->id())],
            'title' => [$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', 'string', Rule::in([CalendarEvent::TYPE_INTERNAL, CalendarEvent::TYPE_MEETING, CalendarEvent::TYPE_DEADLINE, CalendarEvent::TYPE_HEARING])],
            'status' => ['sometimes', 'string', Rule::in([CalendarEvent::STATUS_SCHEDULED, CalendarEvent::STATUS_CONFIRMED, CalendarEvent::STATUS_CANCELLED, CalendarEvent::STATUS_DONE])],
            'starts_at' => [$required, 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'participants' => ['nullable', 'array'],
            'participants.*.organization_member_id' => ['nullable', 'integer', Rule::exists('organization_members', 'id')->where('organization_id', $organizationContext->id())->where('status', OrganizationMember::STATUS_ACTIVE)],
            'participants.*.external_name' => ['nullable', 'string', 'max:255'],
            'participants.*.external_email' => ['nullable', 'email', 'max:255'],
        ]);
    }

    private function createReminder(CalendarEvent $event, int $userId, int $organizationId): void
    {
        InternalReminder::firstOrCreate([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'remindable_type' => $event->getMorphClass(),
            'remindable_id' => $event->id,
            'type' => 'calendar_event',
        ], ['remind_at' => $event->starts_at->copy()->subHour()]);
    }

    private function authorizeEvent(string $ability, CalendarEvent $event): void
    {
        abort_if($event->organization_id !== app(OrganizationContext::class)->id(), Response::HTTP_NOT_FOUND);
        Gate::authorize($ability, $event);
    }
}
