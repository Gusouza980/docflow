<?php

namespace App\Actions\Platform;

use App\Models\Organization;
use App\Models\PlatformAuditLog;
use App\Models\User;
use App\Support\Impersonation;
use App\Support\ResolvesOrganizationOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ImpersonateOrganizationOwner
{
    public function __construct(
        private ResolvesOrganizationOwner $resolvesOrganizationOwner,
        private Impersonation $impersonation,
        private RecordPlatformAuditLog $recordPlatformAuditLog,
    ) {}

    public function execute(User $platformAdmin, Organization $organization, Request $request): User
    {
        abort_unless($platformAdmin->isPlatformAdmin(), 403);

        if ($this->impersonation->isActive($request)) {
            throw ValidationException::withMessages([
                'organization' => 'Já existe uma impersonação ativa. Encerre-a antes de acessar outro tenant.',
            ]);
        }

        $owner = $this->resolvesOrganizationOwner->handle($organization);

        if ($owner === null) {
            throw ValidationException::withMessages([
                'organization' => 'Esta organização não possui um administrador ativo para impersonar.',
            ]);
        }

        Auth::guard('web')->login($owner);
        $this->impersonation->start($request, $platformAdmin, $owner, $organization);

        $this->recordPlatformAuditLog->execute(
            action: PlatformAuditLog::ACTION_IMPERSONATION_STARTED,
            platformAdmin: $platformAdmin,
            subject: $organization,
            metadata: [
                'owner_user_id' => $owner->id,
                'owner_email' => $owner->email,
            ],
            request: $request,
        );

        return $owner;
    }
}
