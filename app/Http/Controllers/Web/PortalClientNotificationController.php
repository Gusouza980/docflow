<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PortalClientAlert;
use App\Support\PresentsPortalClientAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalClientNotificationController extends Controller
{
    public function __construct(private PresentsPortalClientAlert $presenter) {}

    public function index(Request $request): Response|JsonResponse
    {
        $access = auth('portal')->user();
        abort_unless($access, 401);

        $query = PortalClientAlert::query()
            ->where('client_portal_access_id', $access->id)
            ->latest();

        if ($request->wantsJson() && $request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $alerts = $query->limit($request->integer('limit') ?: ($request->wantsJson() ? 20 : 50))->get();

        $payload = [
            'notifications' => $alerts->map(fn (PortalClientAlert $alert): array => $this->presenter->present($alert))->values(),
            'unread_count' => $this->countUnreadForAccess($access->id),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('ClientPortal/Notifications/Index', $payload);
    }

    public function markRead(PortalClientAlert $portalClientAlert, Request $request): RedirectResponse|JsonResponse
    {
        $access = auth('portal')->user();
        abort_unless($access && $portalClientAlert->client_portal_access_id === $access->id, 404);

        $portalClientAlert->update(['read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json([
                'unread_count' => $this->countUnreadForAccess($access->id),
            ]);
        }

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $access = auth('portal')->user();
        abort_unless($access, 401);

        PortalClientAlert::query()
            ->where('client_portal_access_id', $access->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json(['unread_count' => 0]);
        }

        return back()->with('status', 'Notificações marcadas como lidas.');
    }

    public function unreadCount(): JsonResponse
    {
        $access = auth('portal')->user();
        abort_unless($access, 401);

        return response()->json([
            'unread_count' => $this->countUnreadForAccess($access->id),
        ]);
    }

    private function countUnreadForAccess(int $accessId): int
    {
        return PortalClientAlert::query()
            ->where('client_portal_access_id', $accessId)
            ->whereNull('read_at')
            ->count();
    }
}
