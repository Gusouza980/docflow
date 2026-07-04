<?php

namespace App\Actions\ClientProfileUpdates;

use App\Actions\Notifications\NotifyPortalClient;
use App\Models\ClientContact;
use App\Models\ClientPortalAccess;
use App\Models\ClientProfileUpdateRequest;
use App\Models\PortalClientAlert;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReviewClientProfileUpdate
{
    public function __construct(private NotifyPortalClient $notifyPortalClient) {}

    public function approve(ClientProfileUpdateRequest $request, User $reviewer): void
    {
        abort_unless($request->status === ClientProfileUpdateRequest::STATUS_PENDING, 422);

        DB::transaction(function () use ($request, $reviewer): void {
            $request->loadMissing(['portalAccess.client']);

            $access = $request->portalAccess;
            $changes = $request->changes ?? [];

            if (isset($changes['email'])) {
                $access->update(['email' => $changes['email']]);
            }

            if (isset($changes['phone']) || isset($changes['whatsapp'])) {
                $this->applyContactChanges($access, $changes);
            }

            $request->update([
                'status' => ClientProfileUpdateRequest::STATUS_APPROVED,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);
        });

        $request->loadMissing('client');

        $this->notifyPortalClient->execute(
            $request->client,
            'Alteração de perfil aprovada',
            'A equipe aprovou suas alterações cadastrais.',
            route('client-portal.profile', absolute: true),
            PortalClientAlert::TYPE_PROFILE,
        );
    }

    public function reject(ClientProfileUpdateRequest $request, User $reviewer, ?string $notes = null): void
    {
        abort_unless($request->status === ClientProfileUpdateRequest::STATUS_PENDING, 422);

        $request->update([
            'status' => ClientProfileUpdateRequest::STATUS_REJECTED,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        $request->loadMissing('client');

        $body = $notes
            ? "Suas alterações cadastrais não foram aprovadas. Motivo: {$notes}"
            : 'Suas alterações cadastrais não foram aprovadas pela equipe.';

        $this->notifyPortalClient->execute(
            $request->client,
            'Alteração de perfil não aprovada',
            $body,
            route('client-portal.profile', absolute: true),
            PortalClientAlert::TYPE_PROFILE,
        );
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function applyContactChanges(ClientPortalAccess $access, array $changes): void
    {
        $contact = $access->client->contacts()
            ->where('is_primary', true)
            ->first();

        if (! $contact) {
            $contact = ClientContact::create([
                'organization_id' => $access->organization_id,
                'client_id' => $access->client_id,
                'name' => $access->name,
                'email' => $access->email,
                'phone' => $changes['phone'] ?? null,
                'whatsapp' => $changes['whatsapp'] ?? null,
                'is_primary' => true,
            ]);

            return;
        }

        $contact->update([
            'phone' => $changes['phone'] ?? $contact->phone,
            'whatsapp' => $changes['whatsapp'] ?? $contact->whatsapp,
        ]);
    }
}
