<?php

namespace App\Actions\Platform;

use App\Models\Organization;
use App\Models\PlatformAuditLog;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class StopImpersonation
{
    public function __construct(
        private Impersonation $impersonation,
        private RecordPlatformAuditLog $recordPlatformAuditLog,
    ) {}

    public function execute(Request $request): RedirectResponse
    {
        abort_unless($this->impersonation->isActive($request), Response::HTTP_FORBIDDEN, 'Não há impersonação ativa.');

        $organizationId = $this->impersonation->organizationId($request);
        $impersonator = $request->user('admin');
        $owner = $request->user('web');

        Auth::guard('web')->logout();

        $this->impersonation->stop($request);

        if ($impersonator instanceof User && $organizationId) {
            $organization = Organization::query()->find($organizationId);

            $this->recordPlatformAuditLog->execute(
                action: PlatformAuditLog::ACTION_IMPERSONATION_STOPPED,
                platformAdmin: $impersonator,
                subject: $organization,
                metadata: [
                    'owner_user_id' => $owner?->id,
                    'owner_email' => $owner?->email,
                ],
                request: $request,
            );

            if ($organization) {
                return redirect()
                    ->route('admin.organizations.show', $organization)
                    ->with('status', 'Impersonação encerrada.');
            }
        }

        return redirect()->route('admin.dashboard')->with('status', 'Impersonação encerrada.');
    }
}
