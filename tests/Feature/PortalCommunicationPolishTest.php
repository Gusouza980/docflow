<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientPortalAccess;
use App\Models\ClientProfileUpdateRequest;
use App\Models\InternalReminder;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PortalClientAlert;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\PortalClientAlertNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PortalCommunicationPolishTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_profile_update_notifies_team_and_can_be_approved(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $access = $this->createPortalAccess($organization, $client, $user);

        ClientContact::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'phone' => '11999990000',
            'is_primary' => true,
        ]);

        $this->loginPortal($access);

        $this->patch('/client-portal/profile', [
            'name' => 'Maria Atualizada',
            'email' => 'maria.nova@example.com',
            'phone' => '11988887777',
        ])->assertRedirect(route('client-portal.profile'));

        $update = ClientProfileUpdateRequest::query()->firstOrFail();

        $this->assertDatabaseHas('internal_reminders', [
            'user_id' => $user->id,
            'type' => InternalReminder::TYPE_PORTAL_PROFILE_UPDATE,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->patch("/portal/profile-updates/{$update->id}/approve")
            ->assertRedirect(route('portal.index'));

        $access->refresh();
        $this->assertSame('maria.nova@example.com', $access->email);
        $this->assertSame(ClientProfileUpdateRequest::STATUS_APPROVED, $update->fresh()->status);
        $this->assertSame('11988887777', $client->contacts()->where('is_primary', true)->value('phone'));
    }

    public function test_profile_update_can_be_rejected_with_notes(): void
    {
        Notification::fake();

        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $access = $this->createPortalAccess($organization, $client, $user);

        $update = ClientProfileUpdateRequest::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'client_portal_access_id' => $access->id,
            'changes' => ['email' => 'novo@example.com'],
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->patch("/portal/profile-updates/{$update->id}/reject", [
                'review_notes' => 'E-mail corporativo inválido.',
            ])
            ->assertRedirect(route('portal.index'));

        $update->refresh();
        $this->assertSame(ClientProfileUpdateRequest::STATUS_REJECTED, $update->status);
        $this->assertSame('E-mail corporativo inválido.', $update->review_notes);

        Notification::assertSentTo($access, PortalClientAlertNotification::class);
        $this->assertDatabaseHas('portal_client_alerts', [
            'client_portal_access_id' => $access->id,
            'type' => PortalClientAlert::TYPE_PROFILE,
        ]);
    }

    public function test_admin_can_manage_message_templates_via_web(): void
    {
        [$user, $organization] = $this->createMember(OrganizationMember::ROLE_ADMIN);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/message-templates')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('MessageTemplates/Index', false));

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/message-templates', [
                'name' => 'Boas-vindas',
                'channel' => 'portal',
                'purpose' => 'general',
                'subject' => 'Olá',
                'body' => 'Olá, {{ client_name }}!',
                'requires_consent' => true,
                'is_active' => true,
            ])
            ->assertRedirect(route('message-templates.index'));

        $this->assertDatabaseHas('message_templates', [
            'organization_id' => $organization->id,
            'name' => 'Boas-vindas',
        ]);
    }

    public function test_admin_can_manage_announcements_via_web(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/announcements', [
                'title' => 'Feriado',
                'body' => 'Escritório fechado na segunda.',
                'client_id' => $client->id,
                'status' => Announcement::STATUS_PUBLISHED,
            ])
            ->assertRedirect(route('announcements.index'));

        $this->assertDatabaseHas('announcements', [
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Feriado',
        ]);
    }

    public function test_portal_client_can_view_and_mark_in_app_notifications(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $access = $this->createPortalAccess($organization, $client, $user);

        $alert = PortalClientAlert::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'client_portal_access_id' => $access->id,
            'title' => 'Nova resposta',
            'message' => 'Sua solicitação foi respondida.',
        ]);

        $this->loginPortal($access);

        $this->get('/client-portal/notifications')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ClientPortal/Notifications/Index', false)
                ->has('notifications', 1)
                ->where('unread_count', 1));

        $this->patch("/client-portal/notifications/{$alert->id}/read")
            ->assertRedirect();

        $this->assertNotNull($alert->fresh()->read_at);
    }

    public function test_team_ticket_reply_creates_portal_client_alert(): void
    {
        Notification::fake();

        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $access = $this->createPortalAccess($organization, $client, $user);

        $ticket = Ticket::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'visible_to_client' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/clients/{$client->id}/tickets/{$ticket->id}/messages", [
                'body' => 'Recebemos sua solicitação.',
                'visible_to_client' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('portal_client_alerts', [
            'client_portal_access_id' => $access->id,
        ]);

        Notification::assertSentTo($access, PortalClientAlertNotification::class);
    }

    private function createPortalAccess(Organization $organization, Client $client, User $user): ClientPortalAccess
    {
        return ClientPortalAccess::create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'name' => 'Maria Cliente',
            'email' => 'maria@example.com',
            'token_hash' => ClientPortalAccess::makeToken()['hash'],
            'password' => Hash::make('password123'),
            'password_set_at' => now(),
            'onboarding_completed_at' => now(),
        ]);
    }

    private function loginPortal(ClientPortalAccess $access): void
    {
        $this->post('/portal/login', [
            'email' => $access->email,
            'password' => 'password123',
        ])->assertRedirect(route('client-portal.dashboard'));
    }

    /**
     * @return array{0: User, 1: Organization, 2: OrganizationMember}
     */
    private function createMember(string $role, ?Organization $organization = null): array
    {
        $organization ??= Organization::factory()->create();
        $user = User::factory()->create();
        $member = OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);

        return [$user, $organization, $member];
    }

    private function createClient(Organization $organization, OrganizationMember $member): Client
    {
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
            'access_policy' => Client::ACCESS_ALL_MEMBERS,
        ]);
        $client->responsibles()->attach($member->id, ['is_primary' => true]);

        return $client;
    }
}
