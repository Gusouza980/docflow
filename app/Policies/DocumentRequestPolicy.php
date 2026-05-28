<?php

namespace App\Policies;

use App\Models\DocumentRequest;
use App\Models\OrganizationMember;
use App\Models\User;

class DocumentRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DocumentRequest $documentRequest): bool
    {
        $membership = $user->activeMembershipFor($documentRequest->organization);

        if (! $membership) {
            return false;
        }

        return app(ClientPolicy::class)->view($user, $documentRequest->client);
    }

    public function create(User $user): bool
    {
        $membership = $user->activeMembership();

        return $membership && $membership->role !== OrganizationMember::ROLE_READONLY;
    }

    public function update(User $user, DocumentRequest $documentRequest): bool
    {
        $membership = $user->activeMembershipFor($documentRequest->organization);

        if (! $membership || $membership->role === OrganizationMember::ROLE_READONLY) {
            return false;
        }

        return app(ClientPolicy::class)->view($user, $documentRequest->client);
    }

    public function delete(User $user, DocumentRequest $documentRequest): bool
    {
        return $this->update($user, $documentRequest);
    }
}
