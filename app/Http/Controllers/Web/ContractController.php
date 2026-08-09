<?php

namespace App\Http\Controllers\Web;

use App\Actions\Contracts\CancelContract;
use App\Actions\Contracts\CreateContract;
use App\Actions\Contracts\RenewContract;
use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\RenewContractRequest;
use App\Http\Requests\Web\StoreContractRequest;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Contract;
use App\Models\OrganizationMember;
use App\Support\DisplayFormat;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ContractController extends Controller
{
    public function index(Request $request, WebOrganizationContext $webOrganizationContext): Response|RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização.');
        }

        abort_if($membership->role === OrganizationMember::ROLE_READONLY, HttpResponse::HTTP_FORBIDDEN);

        $status = $request->string('status')->toString();
        $expiringSoon = $request->boolean('expiring_soon');

        $contracts = Contract::query()
            ->with('client')
            ->where('organization_id', $membership->organization_id)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($expiringSoon, fn ($query) => $query->expiringWithinDays(30))
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (Contract $contract): array => $this->summary($contract));

        return Inertia::render('Contracts/Index', [
            'contracts' => $contracts,
            'filters' => [
                'status' => $status,
                'expiring_soon' => $expiringSoon,
            ],
            'options' => [
                'statuses' => collect(Contract::statusLabels())
                    ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                    ->values(),
                'billing_intervals' => collect(Contract::billingIntervalLabels())
                    ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                    ->values(),
                'clients' => Client::query()
                    ->where('organization_id', $membership->organization_id)
                    ->orderBy('display_name')
                    ->limit(200)
                    ->get(['id', 'display_name'])
                    ->map(fn (Client $client): array => [
                        'value' => $client->id,
                        'label' => $client->display_name,
                    ]),
            ],
            'can' => [
                'create' => $request->user()->can('create', Contract::class),
                'manage' => $membership->isAdmin() || $membership->isManager(),
            ],
        ]);
    }

    public function store(
        StoreContractRequest $request,
        WebOrganizationContext $webOrganizationContext,
        CreateContract $createContract,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('create', Contract::class);

        $data = $request->validated();
        $client = Client::query()->findOrFail($data['client_id']);
        abort_unless($client->organization_id === $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $client);

        try {
            $contract = $createContract->execute($client, $data);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $auditLog->execute('web.contract.created', $request->user(), $membership->organization, $contract, request: $request);

        return redirect()
            ->route('contracts.show', $contract)
            ->with('status', 'Contrato criado.');
    }

    public function show(
        Contract $contract,
        Request $request,
        WebOrganizationContext $webOrganizationContext,
    ): Response|RedirectResponse {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização.');
        }

        abort_unless($contract->organization_id === $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('view', $contract);

        $contract->load(['client', 'clientServices.serviceType']);

        return Inertia::render('Contracts/Show', [
            'contract' => [
                ...$this->summary($contract),
                'scope_included' => $contract->scope_included,
                'scope_excluded' => $contract->scope_excluded,
                'auto_renew' => $contract->auto_renew,
                'canceled_at' => DisplayFormat::dateTime($contract->canceled_at),
                'cancel_reason' => $contract->cancel_reason,
                'services' => $contract->clientServices->map(fn (ClientService $service): array => [
                    'id' => $service->id,
                    'name' => $service->serviceType?->name,
                    'status' => $service->status,
                    'status_label' => ClientService::statusLabels()[$service->status] ?? $service->status,
                ]),
            ],
            'can' => [
                'manage' => $request->user()->can('manage', $contract),
            ],
        ]);
    }

    public function renew(
        RenewContractRequest $request,
        Contract $contract,
        WebOrganizationContext $webOrganizationContext,
        RenewContract $renewContract,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_unless($contract->organization_id === $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('manage', $contract);

        $endsAt = $request->validated('ends_at');

        try {
            $contract = $renewContract->execute(
                $contract,
                $endsAt ? Carbon::parse($endsAt) : null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $auditLog->execute('web.contract.renewed', $request->user(), $membership->organization, $contract, request: $request);

        return back()->with('status', 'Contrato renovado.');
    }

    public function cancel(
        Request $request,
        Contract $contract,
        WebOrganizationContext $webOrganizationContext,
        CancelContract $cancelContract,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_unless($contract->organization_id === $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('manage', $contract);

        $reason = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:255'],
        ])['cancel_reason'] ?? null;

        try {
            $contract = $cancelContract->execute($contract, $reason);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $auditLog->execute('web.contract.canceled', $request->user(), $membership->organization, $contract, request: $request);

        return back()->with('status', 'Contrato cancelado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Contract $contract): array
    {
        return [
            'id' => $contract->id,
            'code' => $contract->code,
            'status' => $contract->status,
            'status_label' => Contract::statusLabels()[$contract->status] ?? $contract->status,
            'amount_cents' => $contract->amount_cents,
            'billing_interval' => $contract->billing_interval,
            'billing_interval_label' => Contract::billingIntervalLabels()[$contract->billing_interval] ?? $contract->billing_interval,
            'starts_at' => DisplayFormat::date($contract->starts_at),
            'ends_at' => DisplayFormat::date($contract->ends_at),
            'client_id' => $contract->client_id,
            'client_name' => $contract->client?->display_name,
            'client_href' => $contract->client
                ? route('clients.show', ['client' => $contract->client, 'tab' => 'contracts'], absolute: false)
                : null,
            'href' => route('contracts.show', $contract, absolute: false),
            'is_expiring_soon' => $contract->status === Contract::STATUS_ACTIVE
                && $contract->ends_at !== null
                && $contract->ends_at->lte(now()->addDays(30)),
        ];
    }
}
