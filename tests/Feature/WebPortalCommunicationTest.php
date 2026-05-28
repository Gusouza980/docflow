<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\ClientPortalAccess;
use App\Models\CommunicationConsent;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebPortalCommunicationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_view_portal_page_and_create_access(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/portal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Index', false)
                ->has('options.clients', 1));

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/portal/accesses', [
                'client_id' => $client->id,
                'name' => 'Maria Cliente',
                'email' => 'maria@example.com',
                'expires_at' => now()->addDays(10)->toDateString(),
            ])
            ->assertRedirect('/portal')
            ->assertSessionHas('portal_url');

        $portalUrl = session('portal_url');

        $this->actingAs($user)
            ->get('/portal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash.portal_url', $portalUrl));

        $this->assertDatabaseHas('client_portal_accesses', [
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'email' => 'maria@example.com',
            'status' => ClientPortalAccess::STATUS_ACTIVE,
        ]);
    }

    public function test_outbound_message_requires_consent_and_can_create_ticket(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $template = MessageTemplate::factory()->create([
            'organization_id' => $organization->id,
            'created_by_user_id' => $user->id,
            'name' => 'Cobrança',
            'channel' => 'email',
            'purpose' => 'billing',
            'body' => 'Olá {{client_name}}',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/portal/messages', [
                'client_id' => $client->id,
                'message_template_id' => $template->id,
                'channel' => 'email',
                'create_ticket' => true,
            ])
            ->assertRedirect('/portal')
            ->assertSessionHas('error');

        CommunicationConsent::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'recorded_by_user_id' => $user->id,
            'channel' => 'email',
            'purpose' => 'billing',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/portal/messages', [
                'client_id' => $client->id,
                'message_template_id' => $template->id,
                'channel' => 'email',
                'create_ticket' => true,
            ])
            ->assertRedirect('/portal');

        $this->assertDatabaseHas('client_messages', [
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'status' => ClientMessage::STATUS_SENT,
            'body' => "Olá {$client->display_name}",
        ]);
        $this->assertDatabaseHas('tickets', [
            'organization_id' => $organization->id,
            'client_id' => $client->id,
        ]);
    }

    public function test_client_portal_token_is_scoped_and_allows_messages_and_tickets(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $token = ClientPortalAccess::makeToken();
        ClientPortalAccess::create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'name' => 'Maria Cliente',
            'email' => 'maria@example.com',
            'token_hash' => $token['hash'],
            'expires_at' => now()->addMonth(),
        ]);

        $documentRequest = DocumentRequest::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'requested_by_user_id' => $user->id,
            'title' => 'Documentos fiscais',
        ]);
        DocumentRequestItem::factory()->create([
            'organization_id' => $organization->id,
            'document_request_id' => $documentRequest->id,
            'title' => 'Contrato social',
            'instructions' => 'Enviar PDF legível.',
        ]);

        ClientMessage::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'sent_by_user_id' => $user->id,
            'channel' => 'portal',
            'direction' => ClientMessage::DIRECTION_OUTBOUND,
            'status' => ClientMessage::STATUS_SENT,
            'body' => 'Olá, como posso ajudar?',
            'sent_at' => now(),
        ]);

        $this->get("/client-portal/{$token['plain']}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientPortal/Show', false)
                ->where('client.name', $client->display_name)
                ->where('hasPortalCommunicationConsent', false)
                ->has('documentRequests', 1)
                ->where('documentRequests.0.title', 'Documentos fiscais')
                ->has('documentRequests.0.items', 1)
                ->where('documentRequests.0.items.0.title', 'Contrato social')
                ->has('messages', 1)
                ->where('messages.0.direction', ClientMessage::DIRECTION_OUTBOUND)
                ->where('messages.0.body', 'Olá, como posso ajudar?'));

        $this->post("/client-portal/{$token['plain']}/consent")
            ->assertRedirect("/client-portal/{$token['plain']}");

        $this->assertDatabaseHas('communication_consents', [
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'channel' => 'portal',
            'purpose' => 'general',
            'status' => CommunicationConsent::STATUS_GRANTED,
            'source' => 'client_portal',
        ]);

        $this->get("/client-portal/{$token['plain']}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('hasPortalCommunicationConsent', true));

        $this->post("/client-portal/{$token['plain']}/messages", [
            'body' => 'Preciso de retorno.',
        ])->assertRedirect("/client-portal/{$token['plain']}");

        $this->post("/client-portal/{$token['plain']}/tickets", [
            'title' => 'Nova solicitação',
            'description' => 'Enviar segunda via.',
        ])->assertRedirect("/client-portal/{$token['plain']}");

        $this->assertDatabaseHas('client_messages', [
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'direction' => ClientMessage::DIRECTION_INBOUND,
            'body' => 'Preciso de retorno.',
        ]);
        $this->assertDatabaseHas('tickets', [
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Nova solicitação',
            'status' => Ticket::STATUS_NEW,
        ]);
    }

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
