<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPortalAccess;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PortalClientAlert;
use App\Models\Receivable;
use App\Models\ReceivableReminder;
use App\Models\SchedulerRunLog;
use App\Models\User;
use App\Notifications\PortalClientAlertNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinancePortalOperationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_notify_overdue_command_creates_portal_alert_and_notification(): void
    {
        Notification::fake();

        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_FINANCE);
        $client = $this->createClient($organization, $member);
        $access = $this->createPortalAccess($organization, $client, $user);

        $receivable = Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'due_at' => now()->subDays(5)->toDateString(),
            'status' => Receivable::STATUS_OPEN,
        ]);

        $this->artisan('finance:notify-overdue-receivables')->assertSuccessful();

        $this->assertDatabaseHas('portal_client_alerts', [
            'client_id' => $client->id,
            'client_portal_access_id' => $access->id,
            'type' => PortalClientAlert::TYPE_FINANCE,
            'title' => 'Cobrança vencida',
        ]);

        Notification::assertSentTo($access, PortalClientAlertNotification::class, function (PortalClientAlertNotification $notification) use ($receivable): bool {
            return str_contains($notification->message, $receivable->description)
                && str_contains($notification->actionUrl, (string) $receivable->id);
        });

        $this->assertNotNull($receivable->fresh()->last_portal_reminder_at);
    }

    public function test_notify_overdue_command_respects_cooldown_and_does_not_duplicate_alerts(): void
    {
        Notification::fake();

        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_FINANCE);
        $client = $this->createClient($organization, $member);
        $this->createPortalAccess($organization, $client, $user);

        Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'due_at' => now()->subDays(5)->toDateString(),
            'status' => Receivable::STATUS_OPEN,
            'last_portal_reminder_at' => now()->subDays(2),
        ]);

        $this->artisan('finance:notify-overdue-receivables')->assertSuccessful();

        $this->assertDatabaseCount('portal_client_alerts', 0);
        Notification::assertNothingSent();
    }

    public function test_manual_reminder_with_notify_client_notifies_portal(): void
    {
        Notification::fake();

        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_FINANCE);
        $client = $this->createClient($organization, $member);
        $access = $this->createPortalAccess($organization, $client, $user);

        $receivable = Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'due_at' => now()->subDays(3)->toDateString(),
            'status' => Receivable::STATUS_OPEN,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/finance/receivables/{$receivable->id}/reminders", [
                'channel' => ReceivableReminder::CHANNEL_EMAIL,
                'notes' => 'Cliente contatado.',
                'notify_client' => true,
            ])
            ->assertRedirect('/finance');

        $this->assertDatabaseHas('receivable_reminders', [
            'receivable_id' => $receivable->id,
            'channel' => ReceivableReminder::CHANNEL_EMAIL,
        ]);

        $this->assertDatabaseHas('portal_client_alerts', [
            'client_portal_access_id' => $access->id,
            'type' => PortalClientAlert::TYPE_FINANCE,
        ]);

        Notification::assertSentTo($access, PortalClientAlertNotification::class);
        $this->assertNotNull($receivable->fresh()->last_portal_reminder_at);
    }

    public function test_admin_can_access_audit_page_and_assistant_cannot(): void
    {
        [$admin, $organization] = $this->createMember(OrganizationMember::ROLE_ADMIN);

        $this->actingAs($admin)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/audit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Audit/Index', false)
                ->has('observability.scheduler_runs')
                ->has('observability.failed_jobs_count'));

        [$assistant, $assistantOrganization] = $this->createMember(OrganizationMember::ROLE_ASSISTANT, $organization);

        $this->actingAs($assistant)
            ->withSession(['active_organization_id' => $assistantOrganization->id])
            ->get('/audit')
            ->assertForbidden();
    }

    public function test_scheduler_command_writes_run_log(): void
    {
        Notification::fake();

        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_FINANCE);
        $client = $this->createClient($organization, $member);
        $this->createPortalAccess($organization, $client, $user);

        Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'due_at' => now()->subDays(10)->toDateString(),
            'status' => Receivable::STATUS_OPEN,
        ]);

        $this->artisan('finance:notify-overdue-receivables')->assertSuccessful();

        $this->assertDatabaseHas('scheduler_run_logs', [
            'command' => 'finance:notify-overdue-receivables',
            'result' => 'success',
        ]);

        $log = SchedulerRunLog::query()->where('command', 'finance:notify-overdue-receivables')->first();
        $this->assertNotNull($log);
        $this->assertSame(1, $log->meta['notified'] ?? null);
    }

    public function test_portal_finance_page_shows_payment_instructions(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $access = $this->createPortalAccess($organization, $client, $user);

        $organization->update(['payment_instructions' => 'PIX: chave@escritorio.com.br']);

        Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'status' => Receivable::STATUS_OPEN,
        ]);

        $this->actingAs($access, 'portal')
            ->get('/client-portal/finance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientPortal/Finance', false)
                ->where('payment_instructions', 'PIX: chave@escritorio.com.br')
                ->has('receivables', 1));
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

    private function createPortalAccess(Organization $organization, Client $client, User $user): ClientPortalAccess
    {
        return ClientPortalAccess::create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'name' => 'Maria Cliente',
            'email' => 'portal@example.com',
            'token_hash' => ClientPortalAccess::makeToken()['hash'],
            'password' => Hash::make('password123'),
            'password_set_at' => now(),
            'onboarding_completed_at' => now(),
            'status' => ClientPortalAccess::STATUS_ACTIVE,
        ]);
    }
}
