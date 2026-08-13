<?php

namespace App\Http\Controllers\Web;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreServiceTypeRequest;
use App\Models\ServiceType;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ServiceTypeController extends Controller
{
    public function index(Request $request, WebOrganizationContext $webOrganizationContext): Response|RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização.');
        }

        abort_unless($membership->isAdmin() || $membership->isManager(), HttpResponse::HTTP_FORBIDDEN);

        $types = ServiceType::query()
            ->where('organization_id', $membership->organization_id)
            ->orderBy('name')
            ->get()
            ->map(fn (ServiceType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                'description' => $type->description,
                'is_active' => $type->is_active,
                'default_amount_cents' => $type->default_amount_cents,
                'default_billing_interval' => $type->default_billing_interval,
                'default_billing_interval_label' => ServiceType::billingIntervalLabels()[$type->default_billing_interval] ?? null,
            ]);

        return Inertia::render('ServiceTypes/Index', [
            'serviceTypes' => $types,
            'options' => [
                'billing_intervals' => collect(ServiceType::billingIntervalLabels())
                    ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                    ->values(),
            ],
            'can' => [
                'manage' => true,
            ],
        ]);
    }

    public function store(
        StoreServiceTypeRequest $request,
        WebOrganizationContext $webOrganizationContext,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('create', ServiceType::class);

        $data = $request->validated();

        $type = ServiceType::query()->create([
            'organization_id' => $membership->organization_id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'default_amount_cents' => $data['default_amount_cents'] ?? null,
            'default_billing_interval' => $data['default_billing_interval'] ?? null,
        ]);

        $auditLog->execute('web.service_type.created', $request->user(), $membership->organization, $type, request: $request);

        return redirect()->route('service-types.index')->with('status', 'Tipo de serviço criado.');
    }

    public function update(
        StoreServiceTypeRequest $request,
        ServiceType $serviceType,
        WebOrganizationContext $webOrganizationContext,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_unless($serviceType->organization_id === $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $serviceType);

        $data = $request->validated();

        $serviceType->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? $serviceType->is_active,
            'default_amount_cents' => $data['default_amount_cents'] ?? null,
            'default_billing_interval' => $data['default_billing_interval'] ?? null,
        ]);

        $auditLog->execute('web.service_type.updated', $request->user(), $membership->organization, $serviceType, request: $request);

        return redirect()->route('service-types.index')->with('status', 'Tipo de serviço atualizado.');
    }
}
