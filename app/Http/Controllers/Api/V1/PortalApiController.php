<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreClientPortalTicketRequest;
use App\Http\Resources\AnnouncementResource;
use App\Http\Resources\DocumentRequestResource;
use App\Http\Resources\TicketResource;
use App\Models\Announcement;
use App\Models\ClientPortalAccess;
use App\Models\Receivable;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PortalApiController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $access = $this->access($request);

        return response()->json([
            'data' => [
                'client' => ['id' => $access->client->id, 'name' => $access->client->display_name],
                'contact' => ['name' => $access->name, 'email' => $access->email],
            ],
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $access = $this->access($request);

        return response()->json([
            'data' => [
                'document_requests_count' => $access->client->documentRequests()->where('status', 'pending')->count(),
                'open_receivables_cents' => $access->client->receivables()->whereIn('status', ['open', 'partial'])->sum('amount_cents'),
                'open_tickets_count' => $access->client->tickets()->whereNotIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])->count(),
            ],
        ]);
    }

    public function documentRequests(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $access = $this->access($request);

        return DocumentRequestResource::collection(
            $access->client->documentRequests()->with(['client', 'items.category', 'items.document.latestVersion'])->latest()->paginate(15)
        );
    }

    public function receivables(Request $request): JsonResponse
    {
        $access = $this->access($request);

        return response()->json([
            'data' => $access->client->receivables()
                ->with('category')
                ->whereIn('status', ['open', 'partial', 'paid'])
                ->latest()
                ->get()
                ->map(fn (Receivable $receivable): array => [
                    'id' => $receivable->id,
                    'description' => $receivable->description,
                    'status' => $receivable->status,
                    'amount_cents' => $receivable->amount_cents,
                    'paid_amount_cents' => $receivable->paid_amount_cents,
                    'balance_cents' => $receivable->balanceCents(),
                    'due_at' => $receivable->due_at?->toDateString(),
                    'category' => $receivable->category?->name,
                ]),
        ]);
    }

    public function tickets(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $access = $this->access($request);

        return TicketResource::collection(
            $access->client->tickets()->where('visible_to_client', true)->with('messages')->latest()->paginate(15)
        );
    }

    public function storeTicket(StoreClientPortalTicketRequest $request): TicketResource
    {
        $access = $this->access($request);

        $ticket = Ticket::create([
            'organization_id' => $access->organization_id,
            'client_id' => $access->client_id,
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'visible_to_client' => true,
        ]);

        $ticket->messages()->create([
            'organization_id' => $access->organization_id,
            'client_portal_access_id' => $access->id,
            'sender_type' => TicketMessage::SENDER_CLIENT,
            'body' => $request->validated('description'),
            'visible_to_client' => true,
        ]);

        return new TicketResource($ticket->load('messages'));
    }

    public function announcements(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $access = $this->access($request);

        return AnnouncementResource::collection(
            Announcement::query()
                ->where('organization_id', $access->organization_id)
                ->where(function ($query) use ($access): void {
                    $query->whereNull('client_id')->orWhere('client_id', $access->client_id);
                })
                ->where('status', Announcement::STATUS_PUBLISHED)
                ->latest('published_at')
                ->paginate(15)
        );
    }

    private function access(Request $request): ClientPortalAccess
    {
        $token = $request->bearerToken() ?: $request->header('X-Portal-Token');
        abort_unless($token, Response::HTTP_UNAUTHORIZED);

        $access = ClientPortalAccess::findUsableByToken($token);
        abort_unless($access, Response::HTTP_UNAUTHORIZED);

        $access->update(['last_used_at' => now()]);

        return $access;
    }
}
