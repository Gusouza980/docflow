<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPortalAccess;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientHubTicketTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_team_can_create_update_and_reply_to_ticket_from_client_hub(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/clients/{$client->id}/tickets", [
                'title' => 'Segunda via de guia',
                'description' => 'Cliente solicitou segunda via.',
                'priority' => 'high',
                'assigned_to_member_id' => $member->id,
                'visible_to_client' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $ticket = Ticket::query()->where('client_id', $client->id)->first();
        $this->assertNotNull($ticket);
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'body' => 'Cliente solicitou segunda via.',
            'visible_to_client' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get("/clients/{$client->id}/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath('ticket.title', 'Segunda via de guia');

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->patch("/clients/{$client->id}/tickets/{$ticket->id}", [
                'status' => Ticket::STATUS_WAITING_CLIENT,
                'priority' => 'high',
                'assigned_to_member_id' => $member->id,
                'visible_to_client' => true,
            ])
            ->assertRedirect(route('clients.show', ['client' => $client, 'tab' => 'tickets', 'ticket' => $ticket->id]));

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/clients/{$client->id}/tickets/{$ticket->id}/messages", [
                'body' => 'Por favor, envie o comprovante.',
                'visible_to_client' => true,
            ])
            ->assertRedirect(route('clients.show', ['client' => $client, 'tab' => 'tickets', 'ticket' => $ticket->id]));

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $ticket->status);
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'body' => 'Por favor, envie o comprovante.',
            'visible_to_client' => true,
        ]);
    }

    public function test_client_hub_lists_portal_ticket_and_filters_open_tickets(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);

        Ticket::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Chamado aberto',
            'status' => Ticket::STATUS_NEW,
        ]);

        Ticket::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Chamado encerrado',
            'status' => Ticket::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $portalTicket = Ticket::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'opened_by_user_id' => null,
            'title' => 'Chamado do portal',
            'status' => Ticket::STATUS_NEW,
        ]);

        TicketMessage::factory()->create([
            'organization_id' => $organization->id,
            'ticket_id' => $portalTicket->id,
            'sender_type' => TicketMessage::SENDER_CLIENT,
            'body' => 'Preciso de ajuda.',
            'visible_to_client' => true,
        ]);

        ClientPortalAccess::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get("/clients/{$client->id}?tab=tickets&ticket_filter=open")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'tickets')
                ->where('filters.ticket_filter', 'open')
                ->has('hub.tickets', 2)
                ->where('hub.tickets', fn ($tickets) => collect($tickets)->contains(fn ($ticket): bool => $ticket['title'] === 'Chamado do portal' && $ticket['opened_by_portal'] === true)
                    && collect($tickets)->contains(fn ($ticket): bool => $ticket['title'] === 'Chamado aberto')));
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
