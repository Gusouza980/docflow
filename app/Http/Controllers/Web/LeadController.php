<?php

namespace App\Http\Controllers\Web;

use App\Actions\Crm\ConvertLeadToClient;
use App\Actions\Crm\UpdateLeadStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ConvertLeadRequest;
use App\Http\Requests\Web\StoreLeadActivityRequest;
use App\Http\Requests\Web\StoreLeadRequest;
use App\Http\Requests\Web\StoreProposalRequest;
use App\Http\Requests\Web\UpdateLeadStageRequest;
use App\Http\Requests\Web\UpdateProposalStatusRequest;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\OrganizationMember;
use App\Models\Proposal;
use App\Support\Billing\PlanLimitChecker;
use App\Support\DisplayFormat;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class LeadController extends Controller
{
    public function index(
        Request $request,
        WebOrganizationContext $webOrganizationContext,
        PlanLimitChecker $planLimitChecker,
    ): Response|RedirectResponse {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização.');
        }

        $planLimitChecker->assertFeature($membership->organization, 'crm');

        $stage = $request->string('stage')->toString();
        $origin = $request->string('origin')->toString();

        $leads = Lead::query()
            ->with('owner')
            ->where('organization_id', $membership->organization_id)
            ->when($stage !== '', fn ($query) => $query->where('stage', $stage))
            ->when($origin !== '', fn ($query) => $query->where('origin', $origin))
            ->latest('id')
            ->limit(200)
            ->get();

        $grouped = collect(Lead::stages())->mapWithKeys(function (string $stageKey) use ($leads): array {
            return [
                $stageKey => $leads
                    ->where('stage', $stageKey)
                    ->values()
                    ->map(fn (Lead $lead): array => $this->leadSummary($lead))
                    ->all(),
            ];
        });

        return Inertia::render('Leads/Index', [
            'grouped' => $grouped,
            'filters' => [
                'stage' => $stage,
                'origin' => $origin,
            ],
            'options' => [
                'stages' => collect(Lead::stageLabels())->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])->values(),
                'origins' => collect(Lead::originLabels())->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])->values(),
            ],
            'can' => [
                'manage' => $this->canManageCrm($membership),
            ],
        ]);
    }

    public function store(
        StoreLeadRequest $request,
        WebOrganizationContext $webOrganizationContext,
        PlanLimitChecker $planLimitChecker,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $planLimitChecker->assertFeature($membership->organization, 'crm');
        abort_unless($this->canManageCrm($membership), HttpResponse::HTTP_FORBIDDEN);

        $data = $request->validated();

        $lead = Lead::query()->create([
            'organization_id' => $membership->organization_id,
            'owner_user_id' => $data['owner_user_id'] ?? $request->user()->id,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'origin' => $data['origin'] ?? null,
            'stage' => $data['stage'] ?? Lead::STAGE_NEW,
            'estimated_value_cents' => $data['estimated_value_cents'] ?? null,
            'service_interest' => $data['service_interest'] ?? null,
        ]);

        return redirect()
            ->route('leads.show', $lead)
            ->with('status', 'Lead criado.');
    }

    public function show(
        Lead $lead,
        Request $request,
        WebOrganizationContext $webOrganizationContext,
        PlanLimitChecker $planLimitChecker,
    ): Response|RedirectResponse {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização.');
        }

        $planLimitChecker->assertFeature($membership->organization, 'crm');
        abort_unless($lead->organization_id === $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);

        $lead->load([
            'owner',
            'client',
            'activities.createdBy',
            'proposals' => fn ($query) => $query->latest('id'),
        ]);

        return Inertia::render('Leads/Show', [
            'lead' => [
                ...$this->leadSummary($lead),
                'service_interest' => $lead->service_interest,
                'lost_reason' => $lead->lost_reason,
                'converted_at' => DisplayFormat::dateTime($lead->converted_at),
                'client' => $lead->client ? [
                    'id' => $lead->client->id,
                    'display_name' => $lead->client->display_name,
                ] : null,
                'activities' => $lead->activities
                    ->sortByDesc('happened_at')
                    ->values()
                    ->map(fn (LeadActivity $activity): array => [
                        'id' => $activity->id,
                        'type' => $activity->type,
                        'type_label' => LeadActivity::typeLabels()[$activity->type] ?? $activity->type,
                        'body' => $activity->body,
                        'happened_at' => DisplayFormat::dateTime($activity->happened_at),
                        'created_by' => $activity->createdBy?->name,
                    ]),
                'proposals' => $lead->proposals->map(fn (Proposal $proposal): array => [
                    'id' => $proposal->id,
                    'title' => $proposal->title,
                    'amount_cents' => $proposal->amount_cents,
                    'status' => $proposal->status,
                    'status_label' => Proposal::statusLabels()[$proposal->status] ?? $proposal->status,
                    'notes' => $proposal->notes,
                    'sent_at' => DisplayFormat::dateTime($proposal->sent_at),
                    'decided_at' => DisplayFormat::dateTime($proposal->decided_at),
                ]),
            ],
            'options' => [
                'stages' => collect(Lead::stageLabels())->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])->values(),
                'activity_types' => collect(LeadActivity::typeLabels())->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])->values(),
                'proposal_statuses' => collect(Proposal::statusLabels())->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])->values(),
            ],
            'can' => [
                'manage' => $this->canManageCrm($membership),
            ],
        ]);
    }

    public function updateStage(
        UpdateLeadStageRequest $request,
        Lead $lead,
        WebOrganizationContext $webOrganizationContext,
        PlanLimitChecker $planLimitChecker,
        UpdateLeadStage $updateLeadStage,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $planLimitChecker->assertFeature($membership->organization, 'crm');
        abort_unless($lead->organization_id === $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        abort_unless($this->canManageCrm($membership), HttpResponse::HTTP_FORBIDDEN);

        $data = $request->validated();
        $updateLeadStage->execute($lead, $data['stage'], $data['lost_reason'] ?? null);

        return back()->with('status', 'Etapa atualizada.');
    }

    public function storeActivity(
        StoreLeadActivityRequest $request,
        Lead $lead,
        WebOrganizationContext $webOrganizationContext,
        PlanLimitChecker $planLimitChecker,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $planLimitChecker->assertFeature($membership->organization, 'crm');
        abort_unless($lead->organization_id === $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        abort_unless($this->canManageCrm($membership), HttpResponse::HTTP_FORBIDDEN);

        $data = $request->validated();

        LeadActivity::query()->create([
            'lead_id' => $lead->id,
            'created_by_user_id' => $request->user()->id,
            'type' => $data['type'],
            'body' => $data['body'],
            'happened_at' => $data['happened_at'] ?? now(),
        ]);

        return back()->with('status', 'Atividade registrada.');
    }

    public function storeProposal(
        StoreProposalRequest $request,
        Lead $lead,
        WebOrganizationContext $webOrganizationContext,
        PlanLimitChecker $planLimitChecker,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $planLimitChecker->assertFeature($membership->organization, 'crm');
        abort_unless($lead->organization_id === $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        abort_unless($this->canManageCrm($membership), HttpResponse::HTTP_FORBIDDEN);

        $data = $request->validated();

        Proposal::query()->create([
            'lead_id' => $lead->id,
            'title' => $data['title'],
            'amount_cents' => $data['amount_cents'] ?? null,
            'status' => $data['status'] ?? Proposal::STATUS_DRAFT,
            'notes' => $data['notes'] ?? null,
            'sent_at' => ($data['status'] ?? null) === Proposal::STATUS_SENT ? now() : null,
        ]);

        return back()->with('status', 'Proposta criada.');
    }

    public function updateProposalStatus(
        UpdateProposalStatusRequest $request,
        Lead $lead,
        Proposal $proposal,
        WebOrganizationContext $webOrganizationContext,
        PlanLimitChecker $planLimitChecker,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $planLimitChecker->assertFeature($membership->organization, 'crm');
        abort_unless($lead->organization_id === $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        abort_unless($proposal->lead_id === $lead->id, HttpResponse::HTTP_NOT_FOUND);
        abort_unless($this->canManageCrm($membership), HttpResponse::HTTP_FORBIDDEN);

        $status = $request->validated('status');

        $proposal->update([
            'status' => $status,
            'sent_at' => $status === Proposal::STATUS_SENT ? ($proposal->sent_at ?? now()) : $proposal->sent_at,
            'decided_at' => in_array($status, [Proposal::STATUS_ACCEPTED, Proposal::STATUS_REJECTED], true)
                ? now()
                : null,
        ]);

        return back()->with('status', 'Status da proposta atualizado.');
    }

    public function convert(
        ConvertLeadRequest $request,
        Lead $lead,
        WebOrganizationContext $webOrganizationContext,
        PlanLimitChecker $planLimitChecker,
        ConvertLeadToClient $convertLeadToClient,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $planLimitChecker->assertFeature($membership->organization, 'crm');
        abort_unless($lead->organization_id === $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        abort_unless($this->canManageCrm($membership), HttpResponse::HTTP_FORBIDDEN);

        try {
            $client = $convertLeadToClient->execute(
                lead: $lead,
                actor: $membership,
                startOnboarding: (bool) $request->boolean('start_onboarding'),
            );
        } catch (\InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('clients.show', ['client' => $client, 'tab' => 'commercial'])
            ->with('status', 'Lead convertido em cliente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function leadSummary(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'name' => $lead->name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'origin' => $lead->origin,
            'origin_label' => Lead::originLabels()[$lead->origin] ?? $lead->origin,
            'stage' => $lead->stage,
            'stage_label' => $lead->stageLabel(),
            'estimated_value_cents' => $lead->estimated_value_cents,
            'owner_name' => $lead->owner?->name,
            'is_converted' => $lead->isConverted(),
        ];
    }

    private function canManageCrm(OrganizationMember $membership): bool
    {
        return in_array($membership->role, [
            OrganizationMember::ROLE_ADMIN,
            OrganizationMember::ROLE_MANAGER,
            OrganizationMember::ROLE_PROFESSIONAL,
        ], true);
    }
}
