<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Receivable;
use App\Models\Task;
use App\Models\User;
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
