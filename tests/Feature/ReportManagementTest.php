<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\GeneratedReport;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Payment;
use App\Models\Receivable;
use App\Models\SavedReportFilter;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dashboard_api_exposes_operational_indicators_and_alerts(): void
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
        Sanctum::actingAs($user);

        $this->withOrganization($organization)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.tasks.overdue', 1)
            ->assertJsonPath('data.documents.overdue', 1)
            ->assertJsonCount(2, 'data.alerts');
    }

    public function test_reports_page_renders_overview_productivity_documents_and_finance_for_finance_member(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_FINANCE);
        $client = $this->createClient($organization, $member);
        $receivable = Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'amount_cents' => 100000,
            'paid_amount_cents' => 40000,
            'status' => Receivable::STATUS_PARTIAL,
            'due_at' => now()->subDay()->toDateString(),
        ]);
        Payment::factory()->create([
            'organization_id' => $organization->id,
            'receivable_id' => $receivable->id,
            'amount_cents' => 40000,
            'paid_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/reports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index', false)
                ->where('finance.summary.open_receivables_cents', 60000)
                ->where('finance.summary.received_cents', 40000));
    }

    public function test_non_finance_user_cannot_access_finance_report_api(): void
    {
        [$user, $organization] = $this->createMember(OrganizationMember::ROLE_ASSISTANT);
        Sanctum::actingAs($user);

        $this->withOrganization($organization)
            ->getJson('/api/v1/reports/finance')
            ->assertForbidden();
    }

    public function test_can_save_filter_generate_monthly_report_release_and_export_with_audit(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        Sanctum::actingAs($user);

        $this->withOrganization($organization)
            ->postJson('/api/v1/report-filters', [
                'name' => 'Mês atual',
                'report_type' => 'overview',
                'filters' => ['start_date' => now()->startOfMonth()->toDateString()],
                'is_shared' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Mês atual');

        $reportId = $this->withOrganization($organization)
            ->postJson("/api/v1/reports/clients/{$client->id}/monthly", [
                'title' => 'Relatório de abril',
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', GeneratedReport::STATUS_REVIEWED)
            ->json('data.id');

        $this->withOrganization($organization)
            ->patchJson("/api/v1/reports/{$reportId}/release-to-client")
            ->assertOk()
            ->assertJsonPath('data.status', GeneratedReport::STATUS_RELEASED);

        $this->withOrganization($organization)
            ->postJson('/api/v1/reports/export', [
                'report_type' => 'overview',
            ])
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertDatabaseHas('saved_report_filters', [
            'organization_id' => $organization->id,
            'name' => 'Mês atual',
        ]);
        $this->assertDatabaseHas('report_deliveries', [
            'organization_id' => $organization->id,
            'generated_report_id' => $reportId,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'report.exported',
        ]);
    }

    public function test_client_portal_lists_released_reports_only(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $token = \App\Models\ClientPortalAccess::makeToken();
        \App\Models\ClientPortalAccess::create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'name' => 'Cliente',
            'email' => 'cliente@example.com',
            'token_hash' => $token['hash'],
            'expires_at' => now()->addMonth(),
        ]);
        GeneratedReport::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'generated_by_user_id' => $user->id,
            'title' => 'Liberado',
            'status' => GeneratedReport::STATUS_RELEASED,
            'released_at' => now(),
            'payload' => ['tasks' => ['completed' => 2], 'tickets' => ['open' => 1]],
        ]);
        GeneratedReport::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'generated_by_user_id' => $user->id,
            'title' => 'Rascunho',
            'status' => GeneratedReport::STATUS_DRAFT,
        ]);

        $this->get("/client-portal/{$token['plain']}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientPortal/Show', false)
                ->has('reports', 1)
                ->where('reports.0.title', 'Liberado'));
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

    private function createDocumentItem(Organization $organization, Client $client, string $status, string $dueAt): DocumentRequestItem
    {
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

    private function withOrganization(Organization $organization): self
    {
        return $this->withHeader('X-Organization-Id', (string) $organization->id);
    }
}
