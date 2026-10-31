<?php

namespace Tests\Feature;

use App\Actions\Communication\SendMessageBatch;
use App\Automations\Actions\CreateDocumentRequestAction;
use App\Models\AutomationRule;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientMessage;
use App\Models\ClientPortalAccess;
use App\Models\CommunicationConsent;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\MessageBatch;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\Proposal;
use App\Models\Receivable;
use App\Models\SchedulerRunLog;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use App\Notifications\ClientTemplateMessageNotification;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CohesionLoopsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_api_client_store_dispatches_client_created_automation(): void
    {
        $this->seed(PlanSeeder::class);
        [$user, $organization, $member] = $this->memberOnPlan(OrganizationMember::ROLE_ADMIN, 'profissional');
        $template = TaskTemplate::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
        ]);
        TaskTemplateItem::factory()->create([
            'organization_id' => $organization->id,
            'task_template_id' => $template->id,
            'title' => 'Coletar documentos',
        ]);
        AutomationRule::factory()->create([
            'organization_id' => $organization->id,
            'trigger' => AutomationRule::TRIGGER_CLIENT_CREATED,
            'is_active' => true,
            'actions' => [[
                'type' => AutomationRule::ACTION_CREATE_TASKS_FROM_TEMPLATE,
                'params' => ['task_template_id' => $template->id],
            ]],
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Organization-Id', (string) $organization->id)
            ->postJson('/api/v1/clients', [
                'type' => Client::TYPE_INDIVIDUAL,
                'display_name' => 'Cliente API',
                'document_number' => '123.456.789-01',
                'responsible_member_ids' => [$member->id],
                'individual_profile' => ['full_name' => 'Cliente API'],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('tasks', [
            'organization_id' => $organization->id,
            'title' => 'Coletar documentos',
        ]);
    }

    public function test_api_outbound_message_is_queued_instead_of_marked_sent(): void
    {
        Notification::fake();
        [$user, $organization, $member] = $this->member();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        ClientContact::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'email' => 'cliente@example.com',
            'is_primary' => true,
        ]);
        $template = MessageTemplate::factory()->create([
            'organization_id' => $organization->id,
            'created_by_user_id' => $user->id,
            'channel' => MessageTemplate::CHANNEL_EMAIL,
            'requires_consent' => false,
            'body' => 'Olá {{client_name}}',
        ]);
        CommunicationConsent::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'recorded_by_user_id' => $user->id,
            'channel' => 'email',
            'purpose' => 'general',
            'status' => CommunicationConsent::STATUS_GRANTED,
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Organization-Id', (string) $organization->id)
            ->postJson('/api/v1/messages', [
                'client_id' => $client->id,
                'message_template_id' => $template->id,
                'channel' => 'email',
                'direction' => ClientMessage::DIRECTION_OUTBOUND,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('client_messages', [
            'client_id' => $client->id,
            'status' => ClientMessage::STATUS_SENT,
            'body' => "Olá {$client->display_name}",
        ]);
        Notification::assertSentTimes(ClientTemplateMessageNotification::class, 1);
    }

    public function test_paying_the_last_overdue_receivable_restores_client_standing(): void
    {
        [$user, $organization, $member] = $this->member(OrganizationMember::ROLE_FINANCE);
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
            'status' => Client::STATUS_DELINQUENT,
        ]);
        $receivable = Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'amount_cents' => 100000,
            'status' => Receivable::STATUS_OPEN,
            'due_at' => now()->subDays(40)->toDateString(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/plataforma/finance/receivables/{$receivable->id}/payments", [
                'amount_cents' => '1000,00',
                'paid_at' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame(Client::STATUS_ACTIVE, $client->fresh()->status);
    }

    public function test_activating_a_draft_contract_creates_the_receivable_recurrence(): void
    {
        [$user, $organization, $member] = $this->member();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'status' => Contract::STATUS_DRAFT,
            'billing_interval' => Contract::BILLING_MONTH,
            'amount_cents' => 150000,
            'creates_receivable_recurrence' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/plataforma/contracts/{$contract->id}/activate")
            ->assertRedirect();

        $this->assertSame(Contract::STATUS_ACTIVE, $contract->fresh()->status);
        $this->assertDatabaseHas('receivable_recurrences', [
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
        ]);
    }

    public function test_document_request_automation_requires_items(): void
    {
        [$user, $organization, $member] = $this->member();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        $rule = AutomationRule::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $action = app(CreateDocumentRequestAction::class);

        $this->expectException(InvalidArgumentException::class);

        $action->execute($rule, $client, ['title' => 'Documentos']);
    }

    public function test_document_request_automation_creates_items(): void
    {
        [$user, $organization, $member] = $this->member();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        $rule = AutomationRule::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $result = app(CreateDocumentRequestAction::class)->execute($rule, $client, [
            'title' => 'Documentos iniciais',
            'items' => [
                ['title' => 'Contrato social'],
                ['title' => 'RG do sócio'],
            ],
        ]);

        $this->assertSame(2, $result['items']);
        $this->assertDatabaseHas('document_request_items', [
            'document_request_id' => $result['document_request_id'],
            'title' => 'Contrato social',
        ]);
    }

    public function test_accepting_a_proposal_can_create_a_draft_contract(): void
    {
        $this->seed(PlanSeeder::class);
        [$user, $organization, $member] = $this->memberOnPlan(OrganizationMember::ROLE_ADMIN, 'profissional');
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        $lead = Lead::factory()->create([
            'organization_id' => $organization->id,
            'owner_user_id' => $user->id,
            'client_id' => $client->id,
        ]);
        $proposal = Proposal::factory()->create([
            'lead_id' => $lead->id,
            'amount_cents' => 250000,
            'status' => Proposal::STATUS_SENT,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->patch("/plataforma/leads/{$lead->id}/proposals/{$proposal->id}/status", [
                'status' => Proposal::STATUS_ACCEPTED,
                'create_contract' => true,
            ])
            ->assertRedirect();

        $proposal->refresh();
        $this->assertNotNull($proposal->contract_id);
        $this->assertDatabaseHas('contracts', [
            'id' => $proposal->contract_id,
            'client_id' => $client->id,
            'code' => 'PROP-'.$proposal->id,
            'status' => Contract::STATUS_DRAFT,
            'creates_receivable_recurrence' => true,
        ]);
    }

    public function test_accepting_a_proposal_without_a_client_does_not_create_a_contract(): void
    {
        $this->seed(PlanSeeder::class);
        [$user, $organization] = $this->memberOnPlan(OrganizationMember::ROLE_ADMIN, 'profissional');
        $lead = Lead::factory()->create([
            'organization_id' => $organization->id,
            'owner_user_id' => $user->id,
            'client_id' => null,
        ]);
        $proposal = Proposal::factory()->create([
            'lead_id' => $lead->id,
            'status' => Proposal::STATUS_SENT,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->patch("/plataforma/leads/{$lead->id}/proposals/{$proposal->id}/status", [
                'status' => Proposal::STATUS_ACCEPTED,
                'create_contract' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_essencial_plan_cannot_schedule_reports_or_use_advanced_finance(): void
    {
        $this->seed(PlanSeeder::class);
        [$user, $organization, $member] = $this->memberOnPlan(OrganizationMember::ROLE_ADMIN, 'essencial');
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/plataforma/reports/schedules', [
                'name' => 'Mensal',
                'report_type' => 'overview',
                'frequency' => 'monthly',
            ])
            ->assertRedirect(route('organizations.plan.show'));

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/plataforma/finance/recurrences', [
                'client_id' => $client->id,
                'description' => 'Honorários',
                'amount_cents' => '500,00',
                'billing_day' => 10,
                'start_date' => now()->toDateString(),
                'frequency' => 'monthly',
            ])
            ->assertRedirect(route('organizations.plan.show'));
    }

    public function test_assistant_does_not_receive_finance_or_audit_navigation(): void
    {
        $this->seed(PlanSeeder::class);
        [$user, $organization] = $this->memberOnPlan(OrganizationMember::ROLE_ASSISTANT, 'escritorio');

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/plataforma/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions.can_access_finance', false)
                ->where('auth.permissions.can_access_audit', false));
    }

    public function test_portal_session_ends_when_the_plan_drops_the_portal(): void
    {
        $this->seed(PlanSeeder::class);
        [$user, $organization, $member] = $this->memberOnPlan(OrganizationMember::ROLE_ADMIN, 'profissional');
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);

        ClientPortalAccess::create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'name' => 'Maria Cliente',
            'email' => 'maria-downgrade@example.com',
            'token_hash' => ClientPortalAccess::makeToken()['hash'],
            'password' => Hash::make('password123'),
            'password_set_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        $this->post('/portal/login', [
            'email' => 'maria-downgrade@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('client-portal.dashboard'));

        $essencialId = Plan::query()->where('slug', 'essencial')->value('id');
        $organization->update(['plan_id' => $essencialId]);
        $organization->subscription?->update(['plan_id' => $essencialId]);

        $this->get('/client-portal')
            ->assertRedirect(route('portal.login'));
    }

    public function test_dispatch_due_automations_records_a_scheduler_run(): void
    {
        $this->artisan('automations:dispatch-due')->assertSuccessful();

        $this->assertDatabaseHas('scheduler_run_logs', [
            'command' => 'automations:dispatch-due',
            'result' => 'success',
        ]);
        $this->assertSame(1, SchedulerRunLog::query()->count());
    }

    public function test_sending_the_same_message_batch_on_the_same_day_is_idempotent(): void
    {
        [$user, $organization] = $this->member();
        $template = MessageTemplate::factory()->create([
            'organization_id' => $organization->id,
            'created_by_user_id' => $user->id,
            'channel' => MessageTemplate::CHANNEL_EMAIL,
        ]);
        $action = app(SendMessageBatch::class);

        $first = $action->execute($organization, $user, $template, MessageBatch::FILTER_SELECTED, []);
        $second = $action->execute($organization, $user, $template, MessageBatch::FILTER_SELECTED, []);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('message_batches', 1);
        $this->assertDatabaseCount('client_messages', 0);
    }

    /**
     * @return array{0: User, 1: Organization, 2?: OrganizationMember}
     */
    private function member(string $role = OrganizationMember::ROLE_ADMIN): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $member = OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);

        return [$user, $organization, $member];
    }

    /**
     * @return array{0: User, 1: Organization, 2: OrganizationMember}
     */
    private function memberOnPlan(string $role, string $planSlug): array
    {
        $planId = Plan::query()->where('slug', $planSlug)->value('id');
        $organization = Organization::factory()->create(['plan_id' => $planId]);
        $organization->subscription?->update(['plan_id' => $planId]);
        $user = User::factory()->create();
        $member = OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);

        return [$user, $organization, $member];
    }
}
