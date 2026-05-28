<?php

namespace App\Http\Controllers\Web;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreCalendarEventNotesRequest;
use App\Http\Requests\Web\StoreCalendarEventRequest;
use App\Http\Requests\Web\UpdateCalendarEventRequest;
use App\Models\CalendarEvent;
use App\Models\CalendarEventParticipant;
use App\Models\Client;
use App\Models\InternalReminder;
use App\Models\OrganizationMember;
use App\Models\Task;
use App\Support\WebOrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CalendarEventController extends Controller
{
    public function index(Request $request, WebOrganizationContext $webOrganizationContext): Response|RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização para gerenciar a agenda.');
        }

        $events = $this->eventQuery($request, $membership)
            ->with(['client', 'participants.member.user'])
            ->orderBy('starts_at')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Calendar/Index', [
            'events' => [
                'data' => $events->getCollection()->map(fn (CalendarEvent $event): array => $this->eventSummary($event)),
                'meta' => [
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total(),
                ],
            ],
            'filters' => [
                'from' => $request->string('from')->toString(),
                'to' => $request->string('to')->toString(),
                'type' => $request->string('type')->toString(),
                'client_id' => $request->string('client_id')->toString(),
            ],
            'options' => $this->options($membership),
            'can' => [
                'create' => $request->user()->can('create', CalendarEvent::class) && $membership->role !== OrganizationMember::ROLE_READONLY,
            ],
        ]);
    }

    public function store(StoreCalendarEventRequest $request, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($membership->role === OrganizationMember::ROLE_READONLY, HttpResponse::HTTP_FORBIDDEN);
        Gate::authorize('create', CalendarEvent::class);

        $event = DB::transaction(function () use ($request, $membership): CalendarEvent {
            $data = $request->validated();
            $event = CalendarEvent::create([
                ...collect($data)->except(['participants'])->all(),
                'organization_id' => $membership->organization_id,
                'created_by_user_id' => $request->user()->id,
            ]);

            foreach ($data['participants'] ?? [] as $participant) {
                if (! ($participant['organization_member_id'] ?? null) && ! ($participant['external_name'] ?? null)) {
                    continue;
                }

                $event->participants()->create([
                    ...$participant,
                    'organization_id' => $membership->organization_id,
                ]);
            }

            foreach ($event->participants()->whereNotNull('organization_member_id')->with('member')->get() as $participant) {
                $this->createReminder($event, $participant->member->user_id);
            }

            return $event;
        });

        $auditLog->execute('web.calendar_event.created', $request->user(), $membership->organization, $event, request: $request);

        return redirect()->route('calendar.index')->with('status', 'Evento criado.');
    }

    public function update(UpdateCalendarEventRequest $request, CalendarEvent $event, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $this->authorizeEvent('update', $event, $membership);

        DB::transaction(function () use ($event, $request): void {
            $data = $request->validated();
            $event->update(collect($data)->except(['participants'])->all());

            $event->participants()->delete();
            foreach ($data['participants'] ?? [] as $participant) {
                if (! ($participant['organization_member_id'] ?? null) && ! ($participant['external_name'] ?? null)) {
                    continue;
                }

                $event->participants()->create([
                    ...$participant,
                    'organization_id' => $event->organization_id,
                ]);
            }
        });

        $auditLog->execute('web.calendar_event.updated', $request->user(), $event->organization, $event, request: $request);

        return redirect()->route('calendar.index')->with('status', 'Evento atualizado.');
    }

    public function notes(StoreCalendarEventNotesRequest $request, CalendarEvent $event, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $this->authorizeEvent('update', $event, $membership);

        $data = $request->validated();
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
                    'description' => "Criada a partir da reunião: {$event->title}",
                    'priority' => $task['priority'] ?? Task::PRIORITY_NORMAL,
                    'due_at' => $task['due_at'],
                ]);
            }
        });

        $auditLog->execute('web.calendar_event.notes_recorded', $request->user(), $event->organization, $event, request: $request);

        return redirect()->route('calendar.index')->with('status', 'Resumo registrado.');
    }

    private function eventQuery(Request $request, OrganizationMember $membership): Builder
    {
        return CalendarEvent::query()
            ->whereBelongsTo($membership->organization)
            ->when($request->date('from'), fn (Builder $query, $from) => $query->where('starts_at', '>=', $from))
            ->when($request->date('to'), fn (Builder $query, $to) => $query->where('starts_at', '<=', $to))
            ->when($request->string('type')->toString(), fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($request->integer('client_id'), fn (Builder $query, int $clientId) => $query->where('client_id', $clientId))
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

    private function eventSummary(CalendarEvent $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'type' => $event->type,
            'status' => $event->status,
            'starts_at' => $event->starts_at?->toISOString(),
            'ends_at' => $event->ends_at?->toISOString(),
            'location' => $event->location,
            'notes' => $event->notes,
            'client' => $event->client ? ['id' => $event->client->id, 'name' => $event->client->display_name] : null,
            'participants' => $event->participants->map(fn (CalendarEventParticipant $participant): array => [
                'id' => $participant->id,
                'organization_member_id' => $participant->organization_member_id,
                'name' => $participant->member?->user?->name ?? $participant->external_name,
                'email' => $participant->external_email,
            ])->values(),
        ];
    }

    private function options(OrganizationMember $membership): array
    {
        return [
            'clients' => Client::query()->whereBelongsTo($membership->organization)->orderBy('display_name')->get(['id', 'display_name'])->map(fn (Client $client): array => ['value' => $client->id, 'label' => $client->display_name])->values(),
            'members' => OrganizationMember::query()->with('user')->whereBelongsTo($membership->organization)->where('status', OrganizationMember::STATUS_ACTIVE)->get()->map(fn (OrganizationMember $member): array => ['value' => $member->id, 'label' => $member->user?->name ?? "Membro #{$member->id}"])->values(),
        ];
    }

    private function createReminder(CalendarEvent $event, int $userId): void
    {
        InternalReminder::firstOrCreate([
            'organization_id' => $event->organization_id,
            'user_id' => $userId,
            'remindable_type' => $event->getMorphClass(),
            'remindable_id' => $event->id,
            'type' => 'calendar_event',
        ], ['remind_at' => $event->starts_at->copy()->subHour()]);
    }

    private function authorizeEvent(string $ability, CalendarEvent $event, OrganizationMember $membership): void
    {
        abort_if($event->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize($ability, $event);
    }
}
