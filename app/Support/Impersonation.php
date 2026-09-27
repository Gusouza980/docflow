<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use App\Support\Billing\OrganizationAccessibility;
use Illuminate\Http\Request;

class Impersonation
{
    public const SESSION_IMPERSONATOR_ID = 'impersonator_id';

    public const SESSION_ORGANIZATION_ID = 'impersonated_organization_id';

    public function isActive(Request $request): bool
    {
        return $request->session()->has(self::SESSION_IMPERSONATOR_ID);
    }

    public function impersonatorId(Request $request): ?int
    {
        $id = $request->session()->get(self::SESSION_IMPERSONATOR_ID);

        return is_numeric($id) ? (int) $id : null;
    }

    public function organizationId(Request $request): ?int
    {
        $id = $request->session()->get(self::SESSION_ORGANIZATION_ID);

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * @return array{
     *     active: bool,
     *     owner_name: string|null,
     *     organization_name: string|null,
     *     impersonator_name: string|null,
     *     stop_url: string,
     *     organization_suspended: bool,
     *     organization_inaccessible: bool
     * }|null
     */
    public function share(Request $request, ?User $webUser, ?Organization $organization): ?array
    {
        if (! $this->isActive($request) || ! $webUser) {
            return null;
        }

        $impersonator = User::query()->find($this->impersonatorId($request));
        $organization ??= Organization::query()->find($this->organizationId($request));

        return [
            'active' => true,
            'owner_name' => $webUser->name,
            'organization_name' => $organization?->name,
            'impersonator_name' => $impersonator?->name,
            'stop_url' => route('impersonation.stop', absolute: false),
            'organization_suspended' => $organization?->status === Organization::STATUS_SUSPENDED,
            'organization_inaccessible' => $organization !== null
                && ! app(OrganizationAccessibility::class)->isAccessible($organization),
        ];
    }

    public function start(Request $request, User $impersonator, User $owner, Organization $organization): void
    {
        $request->session()->put(self::SESSION_IMPERSONATOR_ID, $impersonator->id);
        $request->session()->put(self::SESSION_ORGANIZATION_ID, $organization->id);
        $request->session()->put('active_organization_id', $organization->id);
    }

    public function stop(Request $request): ?int
    {
        $organizationId = $this->organizationId($request);

        $request->session()->forget([
            self::SESSION_IMPERSONATOR_ID,
            self::SESSION_ORGANIZATION_ID,
            'active_organization_id',
        ]);

        return $organizationId;
    }
}
