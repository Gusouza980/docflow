<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\InternalReminder;
use App\Support\PresentsInternalReminder;
use App\Support\WebOrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class InternalNotificationController extends Controller
{
    public function __construct(private PresentsInternalReminder $presenter) {}

    public function index(Request $request, WebOrganizationContext $webOrganizationContext): Response|JsonResponse
    {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);

        $query = InternalReminder::query()
            ->where('organization_id', $membership->organization_id)
            ->where('user_id', $request->user()->id)
            ->with('remindable')
            ->latest('remind_at');

        if ($request->wantsJson() && $request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $reminders = $query->limit($request->integer('limit') ?: ($request->wantsJson() ? 20 : 50))->get();

        $payload = [
            'notifications' => $reminders->map(fn (InternalReminder $reminder): array => $this->presenter->present($reminder))->values(),
            'unread_count' => $this->countUnreadForUser($membership->organization_id, $request->user()->id),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Notifications/Index', $payload);
    }

    public function markRead(
        InternalReminder $reminder,
        Request $request,
        WebOrganizationContext $webOrganizationContext,
    ): RedirectResponse|JsonResponse {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_unless(
            $reminder->organization_id === $membership->organization_id
            && $reminder->user_id === $request->user()->id,
            HttpResponse::HTTP_NOT_FOUND,
        );

        $reminder->update(['read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json([
                'unread_count' => $this->countUnreadForUser($membership->organization_id, $request->user()->id),
            ]);
        }

        return back();
    }

    public function markAllRead(Request $request, WebOrganizationContext $webOrganizationContext): RedirectResponse|JsonResponse
    {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);

        InternalReminder::query()
            ->where('organization_id', $membership->organization_id)
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json(['unread_count' => 0]);
        }

        return back()->with('status', 'Notificações marcadas como lidas.');
    }

    public function unreadCount(Request $request, WebOrganizationContext $webOrganizationContext): JsonResponse
    {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);

        return response()->json([
            'unread_count' => $this->countUnreadForUser($membership->organization_id, $request->user()->id),
        ]);
    }

    private function countUnreadForUser(int $organizationId, int $userId): int
    {
        return InternalReminder::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }
}
