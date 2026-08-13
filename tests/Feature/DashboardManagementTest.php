<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Proposal;
use App\Models\Receivable;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dashboard_returns_alerts_for_overdue_tasks_and_documents(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);

        Task::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'assigned_to_member_id' => $member->id,
            'status' => Task::STATUS_PENDING,
            'due_at' => now()->subDay()->toDateString(),
        ]);
        $this->createDocumentItem($organization, $client, DocumentRequestItem::STATUS_REQUESTED, now()->subDay()->toDateString());

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index', false)
                ->where('metrics.overdue_tasks', 1)
                ->where('metrics.overdue_documents', 1)
                ->has('alerts', 2)
                ->where('alerts.0.type', 'tasks_overdue')
                ->where('alerts.0.count', 1)
                ->where('alerts.0.severity', 'danger')
                ->where('alerts.0.href', '/tasks?flag=overdue')
                ->where('alerts.1.type', 'documents_overdue')
                ->where('alerts.1.href', '/document-requests?overdue=1'));
    }

    public function test_finance_member_sees_finance_kpis_and_alerts(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_FINANCE);
        $client = $this->createClient($organization, $member);

        Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'amount_cents' => 50000,
            'paid_amount_cents' => 0,
            'status' => Receivable::STATUS_OPEN,
            'due_at' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index', false)
                ->where('can_access_finance', true)
                ->where('metrics.overdue_receivables_cents', 50000)
                ->has('alerts', 1)
                ->where('alerts.0.type', 'receivables_overdue')
                ->where('alerts.0.href', '/finance?status=open'));
    }

    public function test_assistant_does_not_see_finance_kpis_or_alerts(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ASSISTANT);
        $client = $this->createClient($organization, $member);

        Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'amount_cents' => 50000,
            'paid_amount_cents' => 0,
            'status' => Receivable::STATUS_OPEN,
            'due_at' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index', false)
                ->where('can_access_finance', false)
                ->missing('metrics.open_receivables_cents')
                ->missing('metrics.overdue_receivables_cents')
                ->has('alerts', 0));
    }

    public function test_week_period_filter_changes_completed_tasks_metric(): void
    {
        $this->travelTo('2026-07-15 12:00:00');

        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);

        Task::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'assigned_to_member_id' => $member->id,
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => '2026-07-14 10:00:00',
        ]);
        Task::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'assigned_to_member_id' => $member->id,
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => '2026-07-01 10:00:00',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/dashboard?period=week')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index', false)
                ->where('filters.period', 'week')
                ->where('metrics.completed_tasks', 1));

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/dashboard?period=month')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('metrics.completed_tasks', 2));
    }

    public function test_finance_hero_shows_received_delta_and_net_period(): void
    {
        $this->travelTo('2026-08-15 12:00:00');

        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);

        $currentReceivable = Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'amount_cents' => 100000,
            'paid_amount_cents' => 100000,
            'status' => Receivable::STATUS_PAID,
        ]);
        Payment::factory()->create([
            'organization_id' => $organization->id,
            'receivable_id' => $currentReceivable->id,
            'received_by_user_id' => $user->id,
            'amount_cents' => 100000,
            'paid_at' => '2026-08-10',
        ]);

        $previousReceivable = Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'amount_cents' => 40000,
            'paid_amount_cents' => 40000,
            'status' => Receivable::STATUS_PAID,
        ]);
        Payment::factory()->create([
            'organization_id' => $organization->id,
            'receivable_id' => $previousReceivable->id,
            'received_by_user_id' => $user->id,
            'amount_cents' => 40000,
            'paid_at' => '2026-07-10',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/dashboard?period=month')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index', false)
                ->where('value.mode', 'finance')
                ->where('value.received_cents', 100000)
                ->where('value.received_delta_cents', 60000)
                ->where('value.net_period_cents', 100000)
                ->where('value.previous_period.received_cents', 40000));
    }

    public function test_contracts_revenue_shows_mrr_and_at_risk_value(): void
    {
        $this->travelTo('2026-08-15 12:00:00');

        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);

        Contract::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'status' => Contract::STATUS_ACTIVE,
            'amount_cents' => 120000,
            'billing_interval' => Contract::BILLING_MONTH,
            'ends_at' => now()->addMonths(6)->toDateString(),
        ]);
        Contract::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'status' => Contract::STATUS_ACTIVE,
            'amount_cents' => 240000,
            'billing_interval' => Contract::BILLING_YEAR,
            'ends_at' => now()->addDays(10)->toDateString(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('contracts_revenue.mrr_cents', 140000)
                ->where('contracts_revenue.at_risk_cents', 240000)
                ->where('contracts_revenue.expiring_count', 1));
    }

    public function test_profissional_sees_commercial_pipeline_and_essencial_does_not(): void
    {
        $this->seed(PlanSeeder::class);

        $profissionalPlanId = Plan::query()->where('slug', 'profissional')->value('id');
        $essencialPlanId = Plan::query()->where('slug', 'essencial')->value('id');

        $profissionalOrg = Organization::factory()->create(['plan_id' => $profissionalPlanId]);
        $profissionalOrg->subscription?->update(['plan_id' => $profissionalPlanId]);
        [$profissionalUser] = $this->createMember(OrganizationMember::ROLE_ADMIN, $profissionalOrg);

        Lead::factory()->create([
            'organization_id' => $profissionalOrg->id,
            'owner_user_id' => $profissionalUser->id,
            'stage' => Lead::STAGE_PROPOSAL,
            'estimated_value_cents' => 500000,
        ]);
        Lead::factory()->create([
            'organization_id' => $profissionalOrg->id,
            'owner_user_id' => $profissionalUser->id,
            'stage' => Lead::STAGE_WON,
            'estimated_value_cents' => 200000,
            'converted_at' => now(),
        ]);

        $this->actingAs($profissionalUser)
            ->withSession(['active_organization_id' => $profissionalOrg->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_access_crm', true)
                ->where('commercial.pipeline_cents', 500000)
                ->where('commercial.won_leads_cents', 200000)
                ->where('commercial.gained_cents', 200000));

        $essencialOrg = Organization::factory()->create(['plan_id' => $essencialPlanId]);
        $essencialOrg->subscription?->update(['plan_id' => $essencialPlanId]);
        [$essencialUser] = $this->createMember(OrganizationMember::ROLE_ADMIN, $essencialOrg);

        $this->actingAs($essencialUser)
            ->withSession(['active_organization_id' => $essencialOrg->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_access_crm', false)
                ->where('commercial', null));
    }

    public function test_assistant_hero_is_operational_without_finance_cents(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ASSISTANT);
        $client = $this->createClient($organization, $member);

        Task::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'assigned_to_member_id' => $member->id,
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'amount_cents' => 90000,
            'paid_amount_cents' => 0,
            'status' => Receivable::STATUS_OPEN,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_access_finance', false)
                ->where('value.mode', 'operational')
                ->where('value.completed_tasks', 1)
                ->missing('value.received_cents')
                ->missing('metrics.open_receivables_cents'));
    }

    public function test_accepted_proposal_counts_toward_commercial_gain(): void
    {
        $this->seed(PlanSeeder::class);
        $this->travelTo('2026-08-15 12:00:00');

        $planId = Plan::query()->where('slug', 'profissional')->value('id');
        $organization = Organization::factory()->create(['plan_id' => $planId]);
        $organization->subscription?->update(['plan_id' => $planId]);
        [$user] = $this->createMember(OrganizationMember::ROLE_ADMIN, $organization);

        $lead = Lead::factory()->create([
            'organization_id' => $organization->id,
            'owner_user_id' => $user->id,
            'stage' => Lead::STAGE_PROPOSAL,
            'estimated_value_cents' => 100000,
        ]);
        Proposal::factory()->create([
            'lead_id' => $lead->id,
            'amount_cents' => 150000,
            'status' => Proposal::STATUS_ACCEPTED,
            'decided_at' => '2026-08-12 10:00:00',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/dashboard?period=month')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('commercial.accepted_proposals_cents', 150000)
                ->where('commercial.gained_cents', 150000));
    }

    public function test_converted_lead_with_accepted_proposal_is_not_double_counted(): void
    {
        $this->seed(PlanSeeder::class);
        $this->travelTo('2026-08-15 12:00:00');

        $planId = Plan::query()->where('slug', 'profissional')->value('id');
        $organization = Organization::factory()->create(['plan_id' => $planId]);
        $organization->subscription?->update(['plan_id' => $planId]);
        [$user] = $this->createMember(OrganizationMember::ROLE_ADMIN, $organization);

        $lead = Lead::factory()->create([
            'organization_id' => $organization->id,
            'owner_user_id' => $user->id,
            'stage' => Lead::STAGE_WON,
            'estimated_value_cents' => 200000,
            'converted_at' => '2026-08-12 11:00:00',
        ]);
        Proposal::factory()->create([
            'lead_id' => $lead->id,
            'amount_cents' => 180000,
            'status' => Proposal::STATUS_ACCEPTED,
            'decided_at' => '2026-08-11 10:00:00',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/dashboard?period=month')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('commercial.won_leads_cents', 200000)
                ->where('commercial.accepted_proposals_cents', 0)
                ->where('commercial.gained_cents', 200000));
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

    private function createDocumentItem(
        Organization $organization,
        Client $client,
        string $status,
        string $dueAt,
    ): DocumentRequestItem {
        $category = DocumentCategory::factory()->create(['organization_id' => $organization->id]);
        $request = DocumentRequest::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
        ]);

        return DocumentRequestItem::factory()->create([
            'organization_id' => $organization->id,
            'document_request_id' => $request->id,
            'document_category_id' => $category->id,
            'status' => $status,
            'due_at' => $dueAt,
        ]);
    }
}
