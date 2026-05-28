<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Document;
use App\Models\OrganizationMember;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Document $document): bool
    {
        $membership = $user->activeMembershipFor($document->organization);

        if (! $membership) {
            return false;
        }

        if ($document->visibility === Document::VISIBILITY_CONFIDENTIAL) {
            return $membership->isAdmin() || $membership->isManager();
        }

        if ($document->client) {
            return $this->canAccessClient($user, $document->client);
        }

        return true;
    }

    public function create(User $user): bool
    {
        $membership = $user->activeMembership();

        return $membership && $membership->role !== OrganizationMember::ROLE_READONLY;
    }

    public function update(User $user, Document $document): bool
    {
        $membership = $user->activeMembershipFor($document->organization);

        if (! $membership || $membership->role === OrganizationMember::ROLE_READONLY) {
            return false;
        }

        if ($document->client) {
            return $this->canAccessClient($user, $document->client);
        }

        return true;
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    private function canAccessClient(User $user, Client $client): bool
    {
        return app(ClientPolicy::class)->view($user, $client);
    }
}
