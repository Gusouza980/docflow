<?php

namespace App\Http\Controllers\Web;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreAnnouncementRequest;
use App\Models\Announcement;
use App\Models\Client;
use App\Models\OrganizationMember;
use App\Support\DisplayFormat;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AnnouncementController extends Controller
{
    public function index(Request $request, WebOrganizationContext $webOrganizationContext): Response|RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização para gerenciar comunicados.');
        }

        $announcements = Announcement::query()
            ->with('client')
            ->whereBelongsTo($membership->organization)
            ->latest('published_at')
            ->latest()
            ->get();

        return Inertia::render('Announcements/Index', [
            'announcements' => $announcements->map(fn (Announcement $announcement): array => $this->announcementSummary($announcement))->values(),
            'options' => [
                'clients' => Client::query()
                    ->whereBelongsTo($membership->organization)
                    ->orderBy('display_name')
                    ->get(['id', 'display_name'])
                    ->map(fn (Client $client): array => ['value' => $client->id, 'label' => $client->display_name])
                    ->values(),
                'statuses' => [
                    ['value' => Announcement::STATUS_DRAFT, 'label' => 'Rascunho'],
                    ['value' => Announcement::STATUS_PUBLISHED, 'label' => 'Publicado'],
                ],
            ],
            'can' => [
                'create' => $request->user()->can('create', Announcement::class) && $membership->role !== OrganizationMember::ROLE_READONLY,
                'update' => $membership->role !== OrganizationMember::ROLE_READONLY,
            ],
        ]);
    }

    public function store(
        StoreAnnouncementRequest $request,
        WebOrganizationContext $webOrganizationContext,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $this->membership($request, $webOrganizationContext);
        Gate::authorize('create', Announcement::class);

        $data = $request->validated();
        $clientId = $data['client_id'] ?? null;

        if ($clientId) {
            Client::query()->whereBelongsTo($membership->organization)->findOrFail($clientId);
        }

        $announcement = Announcement::create([
            'organization_id' => $membership->organization_id,
            'created_by_user_id' => $request->user()->id,
            'client_id' => $clientId,
            'title' => $data['title'],
            'body' => $data['body'],
            'status' => $data['status'],
            'published_at' => $data['status'] === Announcement::STATUS_PUBLISHED
                ? ($data['published_at'] ?? now())
                : null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        $auditLog->execute('web.announcement.created', $request->user(), $membership->organization, $announcement, request: $request);

        return redirect()->route('announcements.index')->with('status', 'Comunicado criado.');
    }

    public function update(
        StoreAnnouncementRequest $request,
        Announcement $announcement,
        WebOrganizationContext $webOrganizationContext,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $this->membership($request, $webOrganizationContext);
        abort_if($announcement->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $announcement);

        $data = $request->validated();
        $clientId = $data['client_id'] ?? null;

        if ($clientId) {
            Client::query()->whereBelongsTo($membership->organization)->findOrFail($clientId);
        }

        $announcement->update([
            'client_id' => $clientId,
            'title' => $data['title'],
            'body' => $data['body'],
            'status' => $data['status'],
            'published_at' => $data['status'] === Announcement::STATUS_PUBLISHED
                ? ($data['published_at'] ?? $announcement->published_at ?? now())
                : null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        $auditLog->execute('web.announcement.updated', $request->user(), $membership->organization, $announcement, request: $request);

        return redirect()->route('announcements.index')->with('status', 'Comunicado atualizado.');
    }

    public function destroy(
        Announcement $announcement,
        Request $request,
        WebOrganizationContext $webOrganizationContext,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $this->membership($request, $webOrganizationContext);
        abort_if($announcement->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $announcement);

        $announcement->delete();

        $auditLog->execute('web.announcement.deleted', $request->user(), $membership->organization, $announcement, request: $request);

        return redirect()->route('announcements.index')->with('status', 'Comunicado removido.');
    }

    /**
     * @return array<string, mixed>
     */
    private function announcementSummary(Announcement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'status' => $announcement->status,
            'client' => $announcement->client ? [
                'id' => $announcement->client->id,
                'name' => $announcement->client->display_name,
            ] : null,
            'published_at' => DisplayFormat::dateTime($announcement->published_at),
            'expires_at' => DisplayFormat::date($announcement->expires_at),
        ];
    }

    private function membership(Request $request, WebOrganizationContext $webOrganizationContext): OrganizationMember
    {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($membership->role === OrganizationMember::ROLE_READONLY, HttpResponse::HTTP_FORBIDDEN);

        return $membership;
    }
}
