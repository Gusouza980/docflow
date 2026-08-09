<?php

namespace App\Http\Controllers\Web;

use App\Actions\Organizations\RecordAuditLog;
use App\Enums\ClientPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreClientRequest;
use App\Http\Requests\Web\UpdateClientRequest;
use App\Http\Requests\Web\UpdateClientStatusRequest;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientPortalAccess;
use App\Models\ClientService;
use App\Models\ClientTag;
use App\Models\CommunicationConsent;
use App\Models\Contract;
use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\MessageTemplate;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\Proposal;
use App\Models\ServiceType;
use App\Models\Ticket;
use App\Support\Billing\PlanLimitChecker;
use App\Support\BuildsClientPortalDashboard;
use App\Support\BuildsTicketHubPayload;
use App\Support\DisplayFormat;
use App\Support\WebOrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ClientController extends Controller
{
    public function __construct(
        private BuildsClientPortalDashboard $portalDashboard,
        private BuildsTicketHubPayload $ticketPayload,
    ) {}

    public function index(Request $request, WebOrganizationContext $webOrganizationContext): Response|RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização para gerenciar clientes.');
        }

        $clients = $this->clientQuery($request, $membership)
            ->with(['primaryResponsible.user', 'tags'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Clients/Index', [
            'clients' => [
                'data' => $clients->getCollection()->map(fn (Client $client): array => $this->clientSummary($client, $request)),
                'meta' => [
                    'current_page' => $clients->currentPage(),
                    'last_page' => $clients->lastPage(),
                    'per_page' => $clients->perPage(),
                    'total' => $clients->total(),
                ],
            ],
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString(),
                'type' => $request->string('type')->toString(),
                'risk_level' => $request->string('risk_level')->toString(),
                'responsible_member_id' => $request->string('responsible_member_id')->toString(),
            ],
            'options' => $this->options($membership),
            'can' => [
                'create' => $request->user()->can('create', Client::class) && $membership->role !== OrganizationMember::ROLE_READONLY,
            ],
        ]);
    }

    public function store(
        StoreClientRequest $request,
        WebOrganizationContext $webOrganizationContext,
        RecordAuditLog $auditLog,
        PlanLimitChecker $planLimitChecker,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($membership->role === OrganizationMember::ROLE_READONLY, HttpResponse::HTTP_FORBIDDEN);

        $planLimitChecker->assertWithinLimit($membership->organization, 'max_clients', 1);

        $client = DB::transaction(function () use ($request, $membership): Client {
            $data = $request->validated();
            $responsibleIds = array_values(array_unique($data['responsible_member_ids']));
            $primaryResponsibleId = $responsibleIds[0];

            $client = Client::create([
                ...Arr::except($data, ['responsible_member_ids', 'individual_profile', 'company_profile']),
                'organization_id' => $membership->organization_id,
                'primary_responsible_member_id' => $primaryResponsibleId,
            ]);

            $this->syncResponsibles($client, $responsibleIds, $primaryResponsibleId);
            $this->syncProfile($client, $data);

            return $client;
        });

        $auditLog->execute('web.client.created', $request->user(), $membership->organization, $client, request: $request);

        return redirect()->route('clients.show', $client)->with('status', 'Cliente cadastrado.');
    }

    public function show(Client $client, Request $request, WebOrganizationContext $webOrganizationContext): Response
    {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('view', $client);

        $client->load(['primaryResponsible.user', 'individualProfile', 'companyProfile', 'contacts', 'tags', 'responsibles.user', 'accessMembers.user']);

        $events = AuditLog::query()
            ->with('user')
            ->whereMorphedTo('auditable', $client)
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (AuditLog $event): array => [
                'id' => $event->id,
                'action' => $event->action,
                'created_at' => $event->created_at?->toISOString(),
                'user_name' => $event->user?->name,
            ]);

        $tab = $request->string('tab')->toString() ?: 'overview';
        $ticketFilter = $request->string('ticket_filter')->toString() ?: 'open';

        return Inertia::render('Clients/Show', [
            'client' => $this->clientDetail($client, $request),
            'timeline' => $events,
            'tab' => $tab,
            'selectedTicketId' => $request->integer('ticket') ?: null,
            'filters' => [
                'ticket_filter' => $ticketFilter,
            ],
            'hub' => [
                'metrics' => [
                    'documents' => $client->documents()->count(),
                    'document_requests' => $client->documentRequests()->count(),
                    'open_tickets' => $client->tickets()->whereNotIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])->count(),
                    'messages' => $client->messages()->count(),
                    'portal_accesses' => $client->portalAccesses()->where('status', ClientPortalAccess::STATUS_ACTIVE)->count(),
                ],
                'communications' => [
                    'messages' => $this->portalDashboard->messagesForClient($client),
                    'has_portal_consent' => CommunicationConsent::query()
                        ->whereBelongsTo($client)
                        ->where('channel', 'portal')
                        ->where('purpose', 'general')
                        ->where('status', CommunicationConsent::STATUS_GRANTED)
                        ->exists(),
                    'consents' => CommunicationConsent::query()
                        ->whereBelongsTo($client)
                        ->latest()
                        ->limit(10)
                        ->get()
                        ->map(fn (CommunicationConsent $consent): array => [
                            'id' => $consent->id,
                            'channel' => $consent->channel,
                            'purpose' => $consent->purpose,
                            'status' => $consent->status,
                        ]),
                ],
                'portal_accesses' => $client->portalAccesses()
                    ->latest()
                    ->limit(20)
                    ->get()
                    ->map(fn (ClientPortalAccess $access): array => [
                        'id' => $access->id,
                        'name' => $access->name,
                        'email' => $access->email,
                        'status' => $access->status,
                        'expires_at' => DisplayFormat::date($access->expires_at),
                        'last_used_at' => DisplayFormat::dateTime($access->last_used_at),
                        'has_password' => $access->hasCompletedOnboarding(),
                    ]),
                'documents' => $client->documents()
                    ->with('category')
                    ->latest()
                    ->limit(15)
                    ->get()
                    ->map(fn (Document $document): array => [
                        'id' => $document->id,
                        'title' => $document->title,
                        'status' => $document->status,
                        'category' => $document->category?->name,
                        'expires_at' => DisplayFormat::date($document->expires_at),
                        'href' => route('documents.show', $document, absolute: false),
                    ]),
                'document_requests' => $client->documentRequests()
                    ->latest()
                    ->limit(15)
                    ->get()
                    ->map(fn (DocumentRequest $documentRequest): array => [
                        'id' => $documentRequest->id,
                        'title' => $documentRequest->title,
                        'status' => $documentRequest->status,
                        'due_at' => DisplayFormat::date($documentRequest->due_at),
                        'href' => route('document-requests.show', $documentRequest, absolute: false),
                    ]),
                'tickets' => $this->ticketQuery($client, $request, $membership, $ticketFilter)
                    ->withCount('messages')
                    ->with(['assignedTo.user', 'openedBy'])
                    ->latest()
                    ->limit(30)
                    ->get()
                    ->map(fn (Ticket $ticket): array => $this->ticketPayload->listItem($ticket)),
                'commercial' => (
                    $membership->organization_id === $client->organization_id
                    && $membership->canViewCrm()
                    && Plan::query()->exists()
                    && app(PlanLimitChecker::class)->hasFeature($client->organization, 'crm')
                )
                    ? $client->leads()
                        ->with(['activities' => fn ($query) => $query->latest('happened_at')->limit(10), 'proposals'])
                        ->latest('id')
                        ->get()
                        ->map(fn (Lead $lead): array => [
                            'id' => $lead->id,
                            'name' => $lead->name,
                            'stage' => $lead->stage,
                            'stage_label' => $lead->stageLabel(),
                            'origin_label' => Lead::originLabels()[$lead->origin] ?? $lead->origin,
                            'converted_at' => DisplayFormat::dateTime($lead->converted_at),
                            'href' => route('leads.show', $lead, absolute: false),
                            'activities' => $lead->activities->map(fn (LeadActivity $activity): array => [
                                'id' => $activity->id,
                                'type_label' => LeadActivity::typeLabels()[$activity->type] ?? $activity->type,
                                'body' => $activity->body,
                                'happened_at' => DisplayFormat::dateTime($activity->happened_at),
                            ]),
                            'proposals' => $lead->proposals->map(fn (Proposal $proposal): array => [
                                'id' => $proposal->id,
                                'title' => $proposal->title,
                                'amount_cents' => $proposal->amount_cents,
                                'status_label' => Proposal::statusLabels()[$proposal->status] ?? $proposal->status,
                            ]),
                        ])
                    : [],
                'services' => $client->services()
                    ->with(['serviceType', 'assignee.user'])
                    ->latest('id')
                    ->get()
                    ->map(fn (ClientService $service): array => [
                        'id' => $service->id,
                        'service_type_id' => $service->service_type_id,
                        'service_type_name' => $service->serviceType?->name,
                        'status' => $service->status,
                        'status_label' => ClientService::statusLabels()[$service->status] ?? $service->status,
                        'starts_at' => DisplayFormat::date($service->starts_at),
                        'ends_at' => DisplayFormat::date($service->ends_at),
                        'assigned_to_member_id' => $service->assigned_to_member_id,
                        'assignee_name' => $service->assignee?->user?->name,
                        'notes' => $service->notes,
                    ]),
                'contracts' => $client->contracts()
                    ->latest('id')
                    ->get()
                    ->map(fn (Contract $contract): array => [
                        'id' => $contract->id,
                        'code' => $contract->code,
                        'status' => $contract->status,
                        'status_label' => Contract::statusLabels()[$contract->status] ?? $contract->status,
                        'amount_cents' => $contract->amount_cents,
                        'billing_interval_label' => Contract::billingIntervalLabels()[$contract->billing_interval] ?? $contract->billing_interval,
                        'starts_at' => DisplayFormat::date($contract->starts_at),
                        'ends_at' => DisplayFormat::date($contract->ends_at),
                        'href' => route('contracts.show', $contract, absolute: false),
                        'is_expiring_soon' => $contract->status === Contract::STATUS_ACTIVE
                            && $contract->ends_at !== null
                            && $contract->ends_at->lte(now()->addDays(30)),
                    ]),
            ],
            'options' => [
                ...$this->options($membership),
                'ticket_statuses' => BuildsTicketHubPayload::statusOptions(),
                'ticket_priorities' => BuildsTicketHubPayload::priorityOptions(),
                'ticket_filters' => BuildsTicketHubPayload::filterOptions(),
                'message_templates' => MessageTemplate::query()
                    ->whereBelongsTo($membership->organization)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
                    ->map(fn (MessageTemplate $template): array => [
                        'value' => $template->id,
                        'label' => $template->name,
                        'channel' => $template->channel,
                        'body' => $template->body,
                    ]),
                'message_channels' => [
                    ['value' => 'portal', 'label' => 'Portal'],
                    ['value' => 'email', 'label' => 'E-mail'],
                    ['value' => 'whatsapp', 'label' => 'WhatsApp'],
                    ['value' => 'phone', 'label' => 'Telefone'],
                ],
                'service_types' => ServiceType::query()
                    ->where('organization_id', $membership->organization_id)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (ServiceType $type): array => [
                        'value' => $type->id,
                        'label' => $type->name,
                    ]),
                'service_statuses' => collect(ClientService::statusLabels())
                    ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                    ->values(),
            ],
            'can' => [
                'update' => $request->user()->can('update', $client),
            ],
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client, RecordAuditLog $auditLog): RedirectResponse
    {
        $before = $client->toArray();

        DB::transaction(function () use ($request, $client): void {
            $data = $request->validated();
            $responsibleIds = array_values(array_unique($data['responsible_member_ids']));

            $client->update(Arr::except($data, ['responsible_member_ids', 'individual_profile', 'company_profile']));
            $this->syncResponsibles($client, $responsibleIds, $responsibleIds[0]);
            $client->update(['primary_responsible_member_id' => $responsibleIds[0]]);
            $this->syncProfile($client, $data);
        });

        $auditLog->execute('web.client.updated', $request->user(), $client->organization, $client, [
            'before' => Arr::only($before, ['display_name', 'document_number', 'status', 'priority', 'risk_level']),
            'after' => $client->fresh()->only(['display_name', 'document_number', 'status', 'priority', 'risk_level']),
        ], $request);

        return redirect()->route('clients.show', $client)->with('status', 'Cliente atualizado.');
    }

    public function updateStatus(UpdateClientStatusRequest $request, Client $client, RecordAuditLog $auditLog): RedirectResponse
    {
        $data = $request->validated();
        $before = $client->only(['status', 'closure_reason', 'closed_at']);

        $client->update([
            'status' => $data['status'],
            'closure_reason' => $data['closure_reason'] ?? null,
            'closed_at' => $data['status'] === Client::STATUS_CLOSED ? now() : null,
        ]);

        $auditLog->execute('web.client.status_updated', $request->user(), $client->organization, $client, [
            'before' => $before,
            'after' => $client->only(['status', 'closure_reason', 'closed_at']),
        ], $request);

        return redirect()->route('clients.show', $client)->with('status', 'Status atualizado.');
    }

    private function ticketQuery(Client $client, Request $request, OrganizationMember $membership, string $filter): HasMany
    {
        return $client->tickets()
            ->when($filter === 'open', fn ($query) => $query->whereNotIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED]))
            ->when($filter === 'waiting_client', fn ($query) => $query->where('status', Ticket::STATUS_WAITING_CLIENT))
            ->when($filter === 'mine', fn ($query) => $query->where('assigned_to_member_id', $membership->id))
            ->when($filter === 'overdue', fn ($query) => $query
                ->whereDate('due_at', '<', now()->toDateString())
                ->whereNotIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED]))
            ->when($filter === 'closed', fn ($query) => $query->whereIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED]));
    }

    private function clientQuery(Request $request, OrganizationMember $membership): Builder
    {
        return Client::query()
            ->whereBelongsTo($membership->organization)
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('display_name', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%");
                });
            })
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->string('type')->toString(), fn ($query, string $type) => $query->where('type', $type))
            ->when($request->string('risk_level')->toString(), fn ($query, string $riskLevel) => $query->where('risk_level', $riskLevel))
            ->when($request->integer('responsible_member_id'), fn ($query, int $memberId) => $query->whereHas('responsibles', fn ($query) => $query->whereKey($memberId)))
            ->when(! $membership->isAdmin() && ! $membership->isManager(), function ($query) use ($membership): void {
                $query->where(function ($query) use ($membership): void {
                    $query->where('access_policy', Client::ACCESS_ALL_MEMBERS)
                        ->orWhereHas('responsibles', fn ($query) => $query->whereKey($membership->id))
                        ->orWhereHas('accessMembers', fn ($query) => $query->whereKey($membership->id));
                });
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncProfile(Client $client, array $data): void
    {
        if ($client->type === Client::TYPE_INDIVIDUAL && isset($data['individual_profile'])) {
            $client->individualProfile()->updateOrCreate(['client_id' => $client->id], $data['individual_profile']);
        }

        if ($client->type === Client::TYPE_COMPANY && isset($data['company_profile'])) {
            $client->companyProfile()->updateOrCreate(['client_id' => $client->id], $data['company_profile']);
        }
    }

    /**
     * @param  array<int, int>  $responsibleIds
     */
    private function syncResponsibles(Client $client, array $responsibleIds, int $primaryResponsibleId): void
    {
        $client->responsibles()->sync(collect($responsibleIds)
            ->mapWithKeys(fn (int $id) => [$id => ['is_primary' => $id === $primaryResponsibleId]])
            ->all());
    }

    private function clientSummary(Client $client, Request $request): array
    {
        $membership = $request->attributes->get('organization_member');
        $canViewSensitive = $membership?->role !== OrganizationMember::ROLE_READONLY;

        return [
            'id' => $client->id,
            'type' => $client->type,
            'display_name' => $client->display_name,
            'document_number' => $canViewSensitive ? $client->document_number : $this->maskedDocument($client->document_number),
            'status' => $client->status,
            'priority' => $client->priority->value,
            'priority_label' => $client->priority->label(),
            'risk_level' => $client->risk_level,
            'primary_responsible' => $client->primaryResponsible ? [
                'id' => $client->primaryResponsible->id,
                'name' => $client->primaryResponsible->user?->name,
            ] : null,
            'tags' => $client->tags->map(fn (ClientTag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ])->values(),
            'href' => route('clients.show', $client, absolute: false),
        ];
    }

    private function clientDetail(Client $client, Request $request): array
    {
        return [
            ...$this->clientSummary($client, $request),
            'potential_revenue_cents' => $request->attributes->get('organization_member')?->role !== OrganizationMember::ROLE_READONLY ? $client->potential_revenue_cents : null,
            'origin' => $client->origin,
            'access_policy' => $client->access_policy,
            'internal_notes' => $client->internal_notes,
            'entered_at' => $client->entered_at?->toDateString(),
            'closed_at' => $client->closed_at?->toISOString(),
            'closure_reason' => $client->closure_reason,
            'individual_profile' => $client->individualProfile,
            'company_profile' => $client->companyProfile,
            'contacts' => $client->contacts->map(fn ($contact): array => [
                'id' => $contact->id,
                'name' => $contact->name,
                'role' => $contact->role,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'whatsapp' => $contact->whatsapp,
                'type' => $contact->type,
                'is_primary' => $contact->is_primary,
                'notes' => $contact->notes,
            ])->values(),
            'responsibles' => $client->responsibles->map(fn (OrganizationMember $responsible): array => [
                'id' => $responsible->id,
                'is_primary' => (bool) $responsible->pivot->is_primary,
                'name' => $responsible->user?->name,
                'email' => $responsible->user?->email,
            ])->values(),
            'access_members' => $client->accessMembers->map(fn (OrganizationMember $member): array => [
                'id' => $member->id,
                'name' => $member->user?->name,
            ])->values(),
        ];
    }

    private function options(OrganizationMember $membership): array
    {
        return [
            'members' => OrganizationMember::query()
                ->with('user')
                ->whereBelongsTo($membership->organization)
                ->where('status', OrganizationMember::STATUS_ACTIVE)
                ->orderBy('id')
                ->get()
                ->map(fn (OrganizationMember $member): array => [
                    'value' => $member->id,
                    'label' => $member->user?->name.' · '.$member->role,
                ]),
            'tags' => ClientTag::query()
                ->whereBelongsTo($membership->organization)
                ->orderBy('name')
                ->get()
                ->map(fn (ClientTag $tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                ]),
            'priorities' => ClientPriority::options(),
        ];
    }

    private function maskedDocument(?string $document): ?string
    {
        if (! $document) {
            return null;
        }

        return str_repeat('*', max(mb_strlen($document) - 4, 0)).mb_substr($document, -4);
    }
}
