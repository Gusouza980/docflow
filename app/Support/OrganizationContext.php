<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\OrganizationMember;

class OrganizationContext
{
    private ?Organization $organization = null;

    private ?OrganizationMember $membership = null;

    public function set(Organization $organization, OrganizationMember $membership): void
    {
        $this->organization = $organization;
        $this->membership = $membership;
    }

    public function organization(): ?Organization
    {
        return $this->organization;
    }

    public function membership(): ?OrganizationMember
    {
        return $this->membership;
    }

    public function id(): ?int
    {
        return $this->organization?->id;
    }
}
