<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPortalAccess;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\InternalReminder;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\PortalClientAlertNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TeamNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_portal_document_upload_creates_team_notification(): void
    {
        [$user, $organization, $member, $client] = $this->createContext();
        $this->createPortalAccess($organization, $client, $user);

        $documentRequest = DocumentRequest::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'requested_by_user_id' => $user->id,
        ]);

        $item = DocumentRequestItem::factory()->create([
            'organization_id' => $organization->id,
            'document_request_id' => $documentRequest->id,
        ]);

        $this->post('/portal/login', [
            'email' => 'maria@example.com',
            'password' => 'password123',
        ]);

        $this->post("/client-portal/document-items/{$item->id}/upload", [
            'title' => 'Contrato',
            'file' => UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertDatabaseHas('internal_reminders', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'type' => InternalReminder::TYPE_DOCUMENT_RECEIVED_PORTAL,
            'remindable_type' => DocumentRequestItem::class,
            'remindable_id' => $item->id,
        ]);
    }

    public function test_notifications_page_lists_unread_items(): void
    {
        [$user, $organization, $member, $client] = $this->createContext();

        $ticket = Ticket::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
        ]);

        InternalReminder::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'remindable_type' => Ticket::class,
            'remindable_id' => $ticket->id,
            'type' => InternalReminder::TYPE_PORTAL_TICKET_OPENED,
            'remind_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->getJson('/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.title', 'Chamado aberto pelo portal');
    }

    public function test_team_ticket_reply_emails_portal_client(): void
    {
        Notification::fake();

        [$user, $organization, $member, $client] = $this->createContext();
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

        Notification::assertSentTo($access, PortalClientAlertNotification::class);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        [$user, $organization, $member, $client] = $this->createContext();

        $ticket = Ticket::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
        ]);

        $reminder = InternalReminder::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'remindable_type' => Ticket::class,
            'remindable_id' => $ticket->id,
            'type' => InternalReminder::TYPE_PORTAL_TICKET_OPENED,
            'remind_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->patch("/notifications/{$reminder->id}/read")
            ->assertRedirect();

        $this->assertNotNull($reminder->fresh()->read_at);
    }

    /**
     * @return array{0: User, 1: Organization, 2: OrganizationMember, 3: Client}
     */
    private function createContext(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $member = OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => OrganizationMember::ROLE_ADMIN,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
            'access_policy' => Client::ACCESS_ALL_MEMBERS,
        ]);
        $client->responsibles()->attach($member->id, ['is_primary' => true]);

        return [$user, $organization, $member, $client];
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
}
