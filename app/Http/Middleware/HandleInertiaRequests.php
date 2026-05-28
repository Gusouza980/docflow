<?php

namespace App\Http\Middleware;

use App\Models\OrganizationMember;
use App\Support\WebOrganizationContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $membership = app(WebOrganizationContext::class)->membership($request);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                'membership' => $membership ? [
                    'id' => $membership->id,
                    'role' => $membership->role,
                    'organization' => [
                        'id' => $membership->organization->id,
                        'name' => $membership->organization->name,
                    ],
                ] : null,
                'permissions' => [
                    'can_manage_organization' => (bool) ($membership?->isAdmin() || $membership?->isManager()),
                    'can_write' => (bool) ($membership && $membership->role !== OrganizationMember::ROLE_READONLY),
                ],
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
