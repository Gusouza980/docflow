<?php

namespace App\Http\Controllers\Web;

use App\Actions\Finance\SaveOrganizationPaymentGateway;
use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\SaveOrganizationPaymentGatewayRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class OrganizationPaymentGatewayController extends Controller
{
    public function update(
        SaveOrganizationPaymentGatewayRequest $request,
        Organization $organization,
        SaveOrganizationPaymentGateway $saveOrganizationPaymentGateway,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        Gate::authorize('update', $organization);

        try {
            $saveOrganizationPaymentGateway->execute($organization, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('organizations.index')
                ->with('error', $exception->getMessage());
        }

        $auditLog->execute(
            'web.organization.payment_gateway.updated',
            $request->user(),
            $organization,
            $organization,
            ['provider' => 'asaas'],
            $request,
        );

        return redirect()->route('organizations.index')->with('status', 'Asaas do escritório conectado. O cliente poderá pagar no portal.');
    }
}
