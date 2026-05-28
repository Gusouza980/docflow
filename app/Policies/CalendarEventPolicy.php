<?php

namespace App\Policies;

use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\OrganizationMember;
use App\Models\User;

class CalendarEventPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CalendarEvent $calendarEvent): bool
    {
        $membership = $user->activeMembershipFor($calendarEvent->organization);

        if (! $membership) {
            return false;
        }

        return ! $calendarEvent->client || $this->canAccessClient($user, $calendarEvent->client);
    }

    public function create(User $user): bool
    {
        $membership = $user->activeMembership();

        return $membership && $membership->role !== OrganizationMember::ROLE_READONLY;
    }

    public function update(User $user, CalendarEvent $calendarEvent): bool
    {
        $membership = $user->activeMembershipFor($calendarEvent->organization);

        if (! $membership || $membership->role === OrganizationMember::ROLE_READONLY) {
            return false;
        }

        return ! $calendarEvent->client || $this->canAccessClient($user, $calendarEvent->client);
    }

    private function canAccessClient(User $user, Client $client): bool
    {
        return app(ClientPolicy::class)->view($user, $client);
    }
}
