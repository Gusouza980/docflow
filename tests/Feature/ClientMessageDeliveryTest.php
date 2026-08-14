<?php

namespace Tests\Feature;

use App\Automations\AutomationRunner;
use App\Models\AutomationLog;
use App\Models\AutomationRule;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientMessage;
use App\Models\CommunicationConsent;
use App\Models\MessageBatch;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\Receivable;
use App\Models\User;
use App\Notifications\ClientTemplateMessageNotification;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientMessageDeliveryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_batch_preview_lists_overdue_clients_and_skips_without_consent(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $ready = $this->createClient($organization, $member);
        $skipped = $this->createClient($organization, $member, 'Sem Consentimento');
        $this->grantConsent($organization, $ready, $user, MessageTemplate::CHANNEL_EMAIL);
        ClientContact::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $ready->id,
            'email' => 'pronto@example.com',
            'is_primary' => true,
        ]);
        $this->createOverdueReceivable($organization, $ready, $user);
        $this->createOverdueReceivable($organization, $skipped, $user);

        $template = MessageTemplate::factory()->create([
            'organization_id' => $organization->id,
            'created_by_user_id' => $user->id,
            'channel' => MessageTemplate::CHANNEL_EMAIL,
            'body' => 'Olá {{client_name}}, sua cobrança venceu.',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/messages/batch?'.http_build_query([
                'filter' => MessageBatch::FILTER_OVERDUE,
                'message_template_id' => $template->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Messages/Batch', false)
                ->has('preview.ready', 1)
                ->where('preview.ready.0.client_id', $ready->id)
                ->has('preview.skipped', 1)
                ->where('preview.skipped.0.reason', 'Sem consentimento para este canal'));
    }

    public function test_batch_send_emails_ready_recipients_and_records_status(): void
    {
        Notification::fake();

        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $this->grantConsent($organization, $client, $user, MessageTemplate::CHANNEL_EMAIL);
        ClientContact::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'email' => 'cliente@example.com',
            'is_primary' => true,
        ]);
        $this->createOverdueReceivable($organization, $client, $user);

        $template = MessageTemplate::factory()->create([
            'organization_id' => $organization->id,
            'created_by_user_id' => $user->id,
            'channel' => MessageTemplate::CHANNEL_EMAIL,
            'subject' => 'Cobrança em atraso',
            'body' => 'Olá {{client_name}}',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/messages/batch', [
                'filter' => MessageBatch::FILTER_OVERDUE,
                'message_template_id' => $template->id,
            ])
            ->assertRedirect();

        $message = ClientMessage::query()->where('client_id', $client->id)->first();

        $this->assertNotNull($message);
        $this->assertSame(ClientMessage::STATUS_SENT, $message->status);
        $this->assertSame("Olá {$client->display_name}", $message->body);
        $this->assertNotNull($message->batch_id);

        Notification::assertSentTimes(ClientTemplateMessageNotification::class, 1);
    }

    public function test_batch_does_not_include_another_organization(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        [$otherUser, $otherOrganization, $otherMember] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $foreign = $this->createClient($otherOrganization, $otherMember);
        $this->grantConsent($otherOrganization, $foreign, $otherUser, MessageTemplate::CHANNEL_EMAIL);
        ClientContact::factory()->create([
            'organization_id' => $otherOrganization->id,
            'client_id' => $foreign->id,
            'email' => 'outra@example.com',
            'is_primary' => true,
        ]);
        $this->createOverdueReceivable($otherOrganization, $foreign, $otherUser);

        $template = MessageTemplate::factory()->create([
            'organization_id' => $organization->id,
            'created_by_user_id' => $user->id,
            'channel' => MessageTemplate::CHANNEL_EMAIL,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/messages/batch?'.http_build_query([
                'filter' => MessageBatch::FILTER_OVERDUE,
                'message_template_id' => $template->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('preview.ready', [])
                ->where('preview.skipped', []));
    }

    public function test_readonly_cannot_open_batch(): void
    {
        [$user, $organization] = $this->createMember(OrganizationMember::ROLE_READONLY);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/messages/batch')
            ->assertForbidden();
    }

    public function test_whatsapp_message_stays_registered_until_opened(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $this->grantConsent($organization, $client, $user, MessageTemplate::CHANNEL_WHATSAPP);
        ClientContact::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'whatsapp' => '11999998888',
            'is_primary' => true,
        ]);

        $template = MessageTemplate::factory()->create([
            'organization_id' => $organization->id,
            'created_by_user_id' => $user->id,
            'channel' => MessageTemplate::CHANNEL_WHATSAPP,
            'body' => 'Olá {{client_name}}',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/clients/{$client->id}/messages", [
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'message_template_id' => $template->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('whatsapp_url');

        $whatsappUrl = session('whatsapp_url');
        $this->assertStringStartsWith('https://wa.me/5511999998888?text=', $whatsappUrl);

        $message = ClientMessage::query()->where('client_id', $client->id)->first();
        $this->assertSame(ClientMessage::STATUS_REGISTERED, $message->status);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->from("/clients/{$client->id}?tab=communication")
            ->post("/clients/{$client->id}/messages/{$message->id}/whatsapp")
            ->assertRedirect();

        $this->assertSame(ClientMessage::STATUS_SENT, $message->fresh()->status);
    }

    public function test_hub_email_requires_contact_email(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $this->grantConsent($organization, $client, $user, MessageTemplate::CHANNEL_EMAIL);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/clients/{$client->id}/messages", [
                'channel' => MessageTemplate::CHANNEL_EMAIL,
                'body' => 'Texto avulso',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Cadastre um e-mail no contato do cliente.');

        $this->assertDatabaseCount('client_messages', 0);
    }

    public function test_overdue_automation_sends_template_email_once(): void
    {
        Notification::fake();
        $this->seed(PlanSeeder::class);

        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN, $this->profissionalOrganization());
        $client = $this->createClient($organization, $member);
        $this->grantConsent($organization, $client, $user, MessageTemplate::CHANNEL_EMAIL);
        ClientContact::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'email' => 'auto@example.com',
            'name' => 'Contato Auto',
            'is_primary' => true,
        ]);
        $receivable = $this->createOverdueReceivable($organization, $client, $user);

        $template = MessageTemplate::factory()->create([
            'organization_id' => $organization->id,
            'created_by_user_id' => $user->id,
            'channel' => MessageTemplate::CHANNEL_EMAIL,
            'subject' => 'Cobrança de {{client_name}}',
            'body' => 'Valor {{amount}} venceu em {{due_at}}',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/automations', [
                'preset_key' => 'receivable_overdue_email',
                'message_template_id' => $template->id,
                'name' => 'Cobrança automática',
            ])
            ->assertRedirect();

        $runner = app(AutomationRunner::class);
        $runner->dispatch(
            organization: $organization,
            trigger: AutomationRule::TRIGGER_RECEIVABLE_OVERDUE,
            subject: $receivable,
            context: ['client_id' => $client->id],
            dedupeSuffix: $receivable->due_at?->toDateString(),
        );
        $runner->dispatch(
            organization: $organization,
            trigger: AutomationRule::TRIGGER_RECEIVABLE_OVERDUE,
            subject: $receivable,
            context: ['client_id' => $client->id],
            dedupeSuffix: $receivable->due_at?->toDateString(),
        );

        $message = ClientMessage::query()->where('client_id', $client->id)->first();
        $this->assertNotNull($message);
        $this->assertSame('Cobrança de '.$client->display_name, $message->subject);
        $this->assertSame(1, ClientMessage::query()->where('client_id', $client->id)->count());
        $this->assertSame(1, AutomationLog::query()->where('organization_id', $organization->id)->count());
        $this->assertDatabaseHas('automation_logs', [
            'organization_id' => $organization->id,
            'status' => AutomationLog::STATUS_SUCCEEDED,
            'estimated_minutes_saved' => 4,
        ]);

        Notification::assertSentTimes(ClientTemplateMessageNotification::class, 1);
    }

    public function test_automation_skips_without_consent_and_does_not_create_message(): void
    {
        $this->seed(PlanSeeder::class);

        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN, $this->profissionalOrganization());
        $client = $this->createClient($organization, $member);
        $receivable = $this->createOverdueReceivable($organization, $client, $user);
        $template = MessageTemplate::factory()->create([
            'organization_id' => $organization->id,
            'created_by_user_id' => $user->id,
            'channel' => MessageTemplate::CHANNEL_EMAIL,
        ]);

        AutomationRule::factory()->create([
            'organization_id' => $organization->id,
            'trigger' => AutomationRule::TRIGGER_RECEIVABLE_OVERDUE,
            'is_active' => true,
            'actions' => [[
                'type' => AutomationRule::ACTION_SEND_MESSAGE_TEMPLATE,
                'params' => ['message_template_id' => $template->id],
            ]],
        ]);

        app(AutomationRunner::class)->dispatch(
            organization: $organization,
            trigger: AutomationRule::TRIGGER_RECEIVABLE_OVERDUE,
            subject: $receivable,
            context: ['client_id' => $client->id],
            dedupeSuffix: $receivable->due_at?->toDateString(),
        );

        $this->assertDatabaseCount('client_messages', 0);
        $this->assertDatabaseHas('automation_logs', [
            'organization_id' => $organization->id,
            'status' => AutomationLog::STATUS_SUCCEEDED,
        ]);
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

    private function createClient(Organization $organization, OrganizationMember $member, string $name = 'Cliente Lote'): Client
    {
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'display_name' => $name,
            'primary_responsible_member_id' => $member->id,
            'access_policy' => Client::ACCESS_ALL_MEMBERS,
        ]);
        $client->responsibles()->attach($member->id, ['is_primary' => true]);

        return $client;
    }

    private function grantConsent(Organization $organization, Client $client, User $user, string $channel): void
    {
        CommunicationConsent::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'recorded_by_user_id' => $user->id,
            'channel' => $channel,
            'purpose' => 'general',
        ]);
    }

    private function createOverdueReceivable(Organization $organization, Client $client, User $user): Receivable
    {
        return Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'status' => Receivable::STATUS_OPEN,
            'due_at' => now()->subDays(3)->toDateString(),
            'amount_cents' => 25000,
        ]);
    }

    private function profissionalOrganization(): Organization
    {
        $planId = Plan::query()->where('slug', 'profissional')->value('id');

        $organization = Organization::factory()->create(['plan_id' => $planId]);
        $organization->subscription?->update(['plan_id' => $planId]);

        return $organization;
    }
}
