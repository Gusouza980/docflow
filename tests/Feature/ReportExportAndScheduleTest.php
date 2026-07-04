<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\ReportSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReportExportAndScheduleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_web_export_generates_spreadsheet_and_audit_log(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $this->createClient($organization, $member);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/reports/export', [
                'report_type' => 'overview',
                'filters' => [
                    'start_date' => now()->startOfMonth()->toDateString(),
                    'end_date' => now()->endOfMonth()->toDateString(),
                ],
            ]);

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Visão geral operacional', $content);
        $this->assertStringContainsString('Clientes ativos', $content);
        $this->assertStringContainsString('Data de geração', $content);
        $this->assertStringNotContainsString('clients.active', $content);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'web.report.exported',
        ]);
    }

    public function test_assistant_cannot_export_finance_report_via_web(): void
    {
        [$user, $organization] = $this->createMember(OrganizationMember::ROLE_ASSISTANT);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/reports/export', [
                'report_type' => 'finance',
            ])
            ->assertForbidden();
    }

    public function test_monthly_schedule_generates_report_once_and_is_idempotent(): void
    {
        $this->travelTo('2026-07-01 08:00:00');

        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);

        $schedule = ReportSchedule::factory()->create([
            'organization_id' => $organization->id,
            'created_by_user_id' => $user->id,
            'client_id' => $client->id,
            'name' => 'Mensal automático',
            'report_type' => 'overview',
            'frequency' => 'monthly',
            'next_run_at' => '2026-07-01',
            'is_active' => true,
        ]);

        $this->artisan('reports:run-schedules')->assertSuccessful();

        $this->assertDatabaseCount('generated_reports', 1);
        $this->assertDatabaseHas('generated_reports', [
            'organization_id' => $organization->id,
            'report_schedule_id' => $schedule->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
        ]);

        $schedule->refresh();
        $this->assertNotNull($schedule->last_run_at);
        $this->assertSame('2026-08-01', $schedule->next_run_at->toDateString());

        $this->artisan('reports:run-schedules')->assertSuccessful();
        $this->assertDatabaseCount('generated_reports', 1);
    }

    public function test_admin_can_run_schedule_manually_via_web(): void
    {
        $this->travelTo('2026-07-10 09:00:00');

        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $this->createClient($organization, $member);

        $schedule = ReportSchedule::factory()->create([
            'organization_id' => $organization->id,
            'created_by_user_id' => $user->id,
            'name' => 'Produtividade semanal',
            'report_type' => 'productivity',
            'frequency' => 'weekly',
            'next_run_at' => '2026-07-10',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/reports/schedules/{$schedule->id}/run")
            ->assertRedirect(route('reports.index'));

        $this->assertDatabaseHas('generated_reports', [
            'organization_id' => $organization->id,
            'report_schedule_id' => $schedule->id,
            'type' => 'productivity',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'web.report.schedule.executed',
        ]);
    }

    public function test_due_schedules_command_records_failure_without_advancing_next_run(): void
    {
        $this->travelTo('2026-07-01 08:00:00');

        [$user, $organization] = $this->createMember(OrganizationMember::ROLE_ADMIN);

        $schedule = ReportSchedule::factory()->create([
            'organization_id' => $organization->id,
            'created_by_user_id' => $user->id,
            'name' => 'Mensal sem cliente',
            'report_type' => 'client_monthly',
            'client_id' => null,
            'frequency' => 'monthly',
            'next_run_at' => '2026-07-01',
            'is_active' => true,
        ]);

        $this->artisan('reports:run-schedules')->assertSuccessful();

        $schedule->refresh();
        $this->assertSame('2026-07-01', $schedule->next_run_at->toDateString());
        $this->assertNotNull($schedule->last_error);
        $this->assertSame(1, $schedule->consecutive_failures);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'report.schedule.failed',
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
