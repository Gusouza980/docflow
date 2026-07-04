<?php

namespace Tests\Feature;

use App\Enums\CalendarEventType;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientMessage;
use App\Models\ClientPortalAccess;
use App\Models\ClientProfileUpdateRequest;
use App\Models\CommunicationConsent;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\GeneratedReport;
use App\Models\InternalReminder;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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

    public function test_client_portal_invite_redirects_to_onboarding_and_allows_authenticated_access(): void
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

        $this->get("/client-portal/invite/{$token['plain']}")
            ->assertRedirect(route('client-portal.onboarding'));

        $this->get('/client-portal/onboarding')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Onboarding', false)
                ->where('contact.email', 'maria@example.com'));

        $this->post('/client-portal/onboarding', [
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms' => true,
        ])->assertRedirect(route('client-portal.dashboard'));

        $this->get('/client-portal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientPortal/Home', false)
                ->has('summary')
                ->where('portal.client.name', $client->display_name));

        $this->get('/client-portal/messages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientPortal/Messages', false)
                ->where('hasPortalCommunicationConsent', false)
                ->has('messages', 1)
                ->where('messages.0.direction', ClientMessage::DIRECTION_OUTBOUND)
                ->where('messages.0.body', 'Olá, como posso ajudar?'));

        $this->get('/client-portal/documents')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientPortal/Documents/Index', false)
                ->has('documentRequests', 1)
                ->where('documentRequests.0.title', 'Documentos fiscais')
                ->has('documentRequests.0.items', 1)
                ->where('documentRequests.0.items.0.title', 'Contrato social'));

        $this->post('/client-portal/consent')
            ->assertRedirect(route('client-portal.messages'));

        $this->assertDatabaseHas('communication_consents', [
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'channel' => 'portal',
            'purpose' => 'general',
            'status' => CommunicationConsent::STATUS_GRANTED,
            'source' => 'client_portal',
        ]);

        $this->get('/client-portal/messages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('hasPortalCommunicationConsent', true));

        $this->post('/client-portal/messages', [
            'body' => 'Preciso de retorno.',
        ])->assertRedirect(route('client-portal.messages'));

        $this->post('/client-portal/tickets', [
            'title' => 'Nova solicitação',
            'description' => 'Enviar segunda via.',
        ])->assertRedirect();

        $ticket = Ticket::query()->where('client_id', $client->id)->first();
        $this->assertNotNull($ticket);

        $this->get('/client-portal/tickets/'.$ticket->id)
            ->assertOk()
            ->assertJsonPath('ticket.title', 'Nova solicitação')
            ->assertJsonPath('ticket.messages.0.body', 'Enviar segunda via.');

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

    public function test_client_portal_login_works_for_onboarded_access(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);

        ClientPortalAccess::create([
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

        $this->get('/portal/login')->assertOk();

        $this->post('/portal/login', [
            'email' => 'maria@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('client-portal.dashboard'));

        $this->get('/client-portal')->assertOk();
    }

    public function test_client_portal_ticket_thread_hides_internal_notes_and_allows_reply(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);

        $access = ClientPortalAccess::create([
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

        $ticket = Ticket::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Dúvida sobre guia',
            'description' => 'Preciso de orientação.',
            'status' => Ticket::STATUS_WAITING_CLIENT,
            'visible_to_client' => true,
        ]);

        $ticket->messages()->create([
            'organization_id' => $organization->id,
            'client_portal_access_id' => $access->id,
            'sender_type' => TicketMessage::SENDER_CLIENT,
            'body' => 'Preciso de orientação.',
            'visible_to_client' => true,
        ]);

        $ticket->messages()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'sender_type' => TicketMessage::SENDER_INTERNAL,
            'body' => 'Nota interna da equipe.',
            'visible_to_client' => false,
        ]);

        $ticket->messages()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'sender_type' => TicketMessage::SENDER_INTERNAL,
            'body' => 'Envie o comprovante, por favor.',
            'visible_to_client' => true,
        ]);

        $this->post('/portal/login', [
            'email' => 'maria@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('client-portal.dashboard'));

        $this->get('/client-portal/tickets/'.$ticket->id)
            ->assertOk()
            ->assertJsonPath('ticket.needs_response', true)
            ->assertJsonPath('ticket.can_reply', true)
            ->assertJsonCount(2, 'ticket.messages')
            ->assertJsonPath('ticket.messages.1.body', 'Envie o comprovante, por favor.');

        $this->post('/client-portal/tickets/'.$ticket->id.'/messages', [
            'body' => 'Segue o comprovante em anexo.',
        ])->assertRedirect(route('client-portal.tickets.index', ['ticket' => $ticket->id]));

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $ticket->status);

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'client_portal_access_id' => $access->id,
            'sender_type' => TicketMessage::SENDER_CLIENT,
            'body' => 'Segue o comprovante em anexo.',
            'visible_to_client' => true,
        ]);

        $closedTicket = Ticket::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Chamado encerrado',
            'status' => Ticket::STATUS_CLOSED,
            'visible_to_client' => true,
        ]);

        $this->post('/client-portal/tickets/'.$closedTicket->id.'/messages', [
            'body' => 'Tentativa de resposta.',
        ])->assertRedirect(route('client-portal.tickets.index', ['ticket' => $closedTicket->id]))
            ->assertSessionHas('error');

        $this->get('/client-portal/tickets')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('tickets', 2));
    }

    public function test_client_portal_ticket_rating_after_finalization(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);

        $access = ClientPortalAccess::create([
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

        $openTicket = Ticket::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'status' => Ticket::STATUS_IN_PROGRESS,
            'visible_to_client' => true,
        ]);

        $resolvedTicket = Ticket::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Chamado resolvido',
            'status' => Ticket::STATUS_RESOLVED,
            'resolved_at' => now(),
            'visible_to_client' => true,
        ]);

        $this->post('/portal/login', [
            'email' => 'maria@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('client-portal.dashboard'));

        $this->get('/client-portal/tickets/'.$openTicket->id)
            ->assertOk()
            ->assertJsonPath('ticket.can_rate', false);

        $this->get('/client-portal/tickets/'.$resolvedTicket->id)
            ->assertOk()
            ->assertJsonPath('ticket.can_rate', true)
            ->assertJsonPath('ticket.is_finalized', true);

        $this->post('/client-portal/tickets/'.$resolvedTicket->id.'/rating', [
            'rating' => 5,
            'comment' => 'Atendimento excelente.',
        ])->assertRedirect(route('client-portal.tickets.index', ['ticket' => $resolvedTicket->id]))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('ticket_ratings', [
            'ticket_id' => $resolvedTicket->id,
            'client_portal_access_id' => $access->id,
            'rating' => 5,
            'comment' => 'Atendimento excelente.',
        ]);

        $this->get('/client-portal/tickets/'.$resolvedTicket->id)
            ->assertOk()
            ->assertJsonPath('ticket.can_rate', false)
            ->assertJsonPath('ticket.rating.score', 5)
            ->assertJsonPath('ticket.rating.comment', 'Atendimento excelente.');

        $this->post('/client-portal/tickets/'.$resolvedTicket->id.'/rating', [
            'rating' => 3,
        ])->assertRedirect(route('client-portal.tickets.index', ['ticket' => $resolvedTicket->id]))
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get("/clients/{$client->id}/tickets/{$resolvedTicket->id}")
            ->assertOk()
            ->assertJsonPath('ticket.rating.score', 5)
            ->assertJsonPath('ticket.rating.comment', 'Atendimento excelente.');
    }

    public function test_legacy_client_portal_token_route_redirects_to_invite_flow(): void
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
        ]);

        $this->get("/client-portal/{$token['plain']}")
            ->assertRedirect(route('client-portal.invite', ['token' => $token['plain']]));
    }

    public function test_client_hub_portal_message_appears_in_client_portal_chat(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);

        ClientPortalAccess::create([
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

        CommunicationConsent::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'recorded_by_user_id' => $user->id,
            'channel' => 'portal',
            'purpose' => 'general',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/clients/{$client->id}/messages", [
                'channel' => 'portal',
                'body' => 'Mensagem enviada pelo hub do cliente.',
            ])
            ->assertRedirect(route('clients.show', ['client' => $client, 'tab' => 'communication']))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('client_messages', [
            'client_id' => $client->id,
            'channel' => 'portal',
            'direction' => ClientMessage::DIRECTION_OUTBOUND,
            'body' => 'Mensagem enviada pelo hub do cliente.',
        ]);

        $this->post('/portal/login', [
            'email' => 'maria@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('client-portal.dashboard'));

        $this->get('/client-portal/messages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientPortal/Messages', false)
                ->has('messages', 1)
                ->where('messages.0.body', 'Mensagem enviada pelo hub do cliente.')
                ->where('messages.0.direction', ClientMessage::DIRECTION_OUTBOUND));
    }

    public function test_client_portal_can_upload_document_request_item(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);

        ClientPortalAccess::create([
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

        $documentRequest = DocumentRequest::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'requested_by_user_id' => $user->id,
        ]);

        $item = DocumentRequestItem::factory()->create([
            'organization_id' => $organization->id,
            'document_request_id' => $documentRequest->id,
            'title' => 'Contrato social',
        ]);

        $this->post('/portal/login', [
            'email' => 'maria@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('client-portal.dashboard'));

        $file = UploadedFile::fake()->create('contrato.pdf', 120, 'application/pdf');

        $this->post("/client-portal/document-items/{$item->id}/upload", [
            'title' => 'Contrato social',
            'file' => $file,
        ])->assertRedirect(route('client-portal.documents.show', $documentRequest))
            ->assertSessionHas('status');

        $item->refresh();
        $this->assertSame(DocumentRequestItem::STATUS_RECEIVED, $item->status);
        $this->assertNotNull($item->document_id);
    }

    public function test_client_portal_ticket_message_accepts_attachment(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);

        ClientPortalAccess::create([
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

        $ticket = Ticket::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'status' => Ticket::STATUS_IN_PROGRESS,
            'visible_to_client' => true,
        ]);

        $this->post('/portal/login', [
            'email' => 'maria@example.com',
            'password' => 'password123',
        ]);

        $file = UploadedFile::fake()->create('comprovante.pdf', 100, 'application/pdf');

        $this->post("/client-portal/tickets/{$ticket->id}/messages", [
            'body' => 'Segue comprovante.',
            'attachment' => $file,
        ])->assertRedirect(route('client-portal.tickets.index', ['ticket' => $ticket->id]));

        $this->assertDatabaseHas('ticket_message_attachments', [
            'original_name' => 'comprovante.pdf',
        ]);

        $this->get("/client-portal/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath('ticket.messages.0.attachments.0.original_name', 'comprovante.pdf');
    }

    public function test_client_portal_can_confirm_meeting_and_notify_team(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $access = $this->createPortalAccess($organization, $client, $user);

        $event = CalendarEvent::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'type' => CalendarEventType::Meeting,
            'requires_portal_confirmation' => true,
            'portal_confirmation_status' => CalendarEvent::PORTAL_CONFIRMATION_PENDING,
            'starts_at' => now()->addDays(2),
        ]);

        $this->loginPortal($access);

        $this->get('/client-portal/meetings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientPortal/Meetings', false)
                ->has('meetings', 1)
                ->where('meetings.0.can_confirm', true));

        $this->patch("/client-portal/meetings/{$event->id}/confirm", [
            'action' => CalendarEvent::PORTAL_CONFIRMATION_CONFIRMED,
        ])->assertRedirect(route('client-portal.meetings'))
            ->assertSessionHas('status');

        $event->refresh();
        $this->assertSame(CalendarEvent::PORTAL_CONFIRMATION_CONFIRMED, $event->portal_confirmation_status);
        $this->assertSame(CalendarEvent::STATUS_CONFIRMED, $event->status);
        $this->assertDatabaseHas('internal_reminders', [
            'user_id' => $user->id,
            'type' => 'meeting_portal_confirmation',
        ]);
    }

    public function test_client_portal_profile_updates_name_directly_and_queues_email_change(): void
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
        ])->assertRedirect(route('client-portal.profile'))
            ->assertSessionHas('status');

        $access->refresh();
        $this->assertSame('Maria Atualizada', $access->name);
        $this->assertSame('maria@example.com', $access->email);

        $this->assertDatabaseHas('client_profile_update_requests', [
            'client_portal_access_id' => $access->id,
            'status' => ClientProfileUpdateRequest::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('internal_reminders', [
            'user_id' => $user->id,
            'type' => InternalReminder::TYPE_PORTAL_PROFILE_UPDATE,
        ]);
    }

    public function test_client_portal_can_download_released_report(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $access = $this->createPortalAccess($organization, $client, $user);

        $report = GeneratedReport::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'generated_by_user_id' => $user->id,
            'status' => GeneratedReport::STATUS_RELEASED,
            'released_at' => now(),
            'payload' => ['tasks' => ['completed' => 3]],
        ]);

        $this->loginPortal($access);

        $this->get("/client-portal/reports/{$report->id}/download")
            ->assertOk()
            ->assertHeader('content-disposition');

        $report->refresh();
        $this->assertNotNull($report->last_viewed_at);
    }

    public function test_client_portal_can_open_ticket_from_outbound_message(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $access = $this->createPortalAccess($organization, $client, $user);

        $message = ClientMessage::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'sent_by_user_id' => $user->id,
            'channel' => 'portal',
            'direction' => ClientMessage::DIRECTION_OUTBOUND,
            'body' => 'Precisamos conversar sobre o contrato.',
        ]);

        $this->loginPortal($access);

        $this->post("/client-portal/messages/{$message->id}/ticket")
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'client_id' => $client->id,
            'source_message_id' => $message->id,
        ]);

        $message->refresh();
        $this->assertNotNull($message->ticket_id);
    }

    public function test_client_hub_can_open_ticket_from_inbound_message(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);

        $message = ClientMessage::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'channel' => 'portal',
            'direction' => ClientMessage::DIRECTION_INBOUND,
            'body' => 'Tenho dúvidas sobre a cobrança.',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/clients/{$client->id}/messages/{$message->id}/ticket")
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('tickets', [
            'client_id' => $client->id,
            'source_message_id' => $message->id,
            'opened_by_user_id' => $user->id,
        ]);
    }

    public function test_unread_message_and_ticket_badges_update_after_reading(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $access = $this->createPortalAccess($organization, $client, $user);

        ClientMessage::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'sent_by_user_id' => $user->id,
            'channel' => 'portal',
            'direction' => ClientMessage::DIRECTION_OUTBOUND,
            'body' => 'Nova mensagem do escritório.',
        ]);

        $ticket = Ticket::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'visible_to_client' => true,
        ]);

        TicketMessage::factory()->create([
            'organization_id' => $organization->id,
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'sender_type' => TicketMessage::SENDER_INTERNAL,
            'body' => 'Resposta da equipe.',
            'visible_to_client' => true,
        ]);

        $this->loginPortal($access);

        $this->get('/client-portal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('portal.nav.unreadMessages', 1)
                ->where('portal.nav.unreadTicketReplies', 1));

        $this->get('/client-portal/messages')->assertOk();
        $this->get("/client-portal/tickets/{$ticket->id}")->assertOk();

        $this->get('/client-portal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('portal.nav.unreadMessages', 0)
                ->where('portal.nav.unreadTicketReplies', 0));
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
