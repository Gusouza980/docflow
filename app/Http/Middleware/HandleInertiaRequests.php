<?php

namespace App\Http\Middleware;

use App\Models\InternalReminder;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\User;
use App\Support\Billing\OrganizationAccessibility;
use App\Support\Billing\PlanLimitChecker;
use App\Support\BuildsClientPortalDashboard;
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
        $webUser = $user instanceof User ? $user : null;
        $membership = $webUser ? app(WebOrganizationContext::class)->membership($request) : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $webUser ? [
                    'id' => $webUser->id,
                    'name' => $webUser->name,
                    'email' => $webUser->email,
                    'is_platform_admin' => $webUser->isPlatformAdmin(),
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
                    'can_access_crm' => (bool) ($membership
                        && $membership->canViewCrm()
                        && Plan::query()->exists()
                        && app(PlanLimitChecker::class)->hasFeature($membership->organization, 'crm')),
                    'can_access_automations' => (bool) ($membership
                        && ($membership->isAdmin() || $membership->isManager())
                        && Plan::query()->exists()
                        && app(PlanLimitChecker::class)->hasFeature($membership->organization, 'automations')),
                ],
                'plan_summary' => function () use ($webUser, $membership) {
                    if (! $webUser || ! $membership?->isAdmin() || ! Plan::query()->exists()) {
                        return null;
                    }

                    return app(PlanLimitChecker::class)->usageSummary($membership->organization);
                },
                'subscription_summary' => function () use ($membership) {
                    if (! $membership) {
                        return null;
                    }

                    return app(OrganizationAccessibility::class)->summaryFor($membership->organization);
                },
                'notifications' => [
                    'unread_count' => ($webUser && $membership)
                        ? InternalReminder::query()
                            ->where('organization_id', $membership->organization_id)
                            ->where('user_id', $user->id)
                            ->whereNull('read_at')
                            ->count()
                        : 0,
                ],
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
                'portal_url' => fn () => $request->session()->get('portal_url'),
                'whatsapp_url' => fn () => $request->session()->get('whatsapp_url'),
            ],
            'portalAuth' => [
                'access' => auth('portal')->user() ? [
                    'name' => auth('portal')->user()->name,
                    'email' => auth('portal')->user()->email,
                    'client' => auth('portal')->user()->client?->display_name,
                ] : null,
            ],
            'portal' => function () {
                $access = auth('portal')->user();

                if (! $access) {
                    return null;
                }

                $access->loadMissing(['organization', 'client']);
                $dashboard = app(BuildsClientPortalDashboard::class);

                return [
                    'client' => [
                        'id' => $access->client->id,
                        'name' => $access->client->display_name,
                        'organization' => [
                            'id' => $access->organization->id,
                            'name' => $access->organization->name,
                        ],
                        'contact' => ['name' => $access->name, 'email' => $access->email],
                    ],
                    'nav' => $dashboard->navigationCounts($access),
                    'notifications' => [
                        'unread_count' => $dashboard->unreadAlertsCount($access),
                    ],
                ];
            },
        ];
    }
}
