<?php

namespace Tests\Feature;

use App\Actions\Billing\MarkInvoicePaid;
use App\Jobs\ProcessBillingWebhook;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Notifications\SubscriptionTrialEndingNotification;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BillingLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        config(['docflow.billing.webhook_secret' => 'test-webhook-secret']);
    }

    public function test_expired_trial_generates_open_invoice_via_command(): void
    {
        [$user, $organization] = $this->createAdminContext();

        $organization->subscription->update([
            'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->artisan('billing:generate-invoices')->assertSuccessful();

        $this->assertDatabaseHas('subscription_invoices', [
            'organization_id' => $organization->id,
            'status' => SubscriptionInvoice::STATUS_OPEN,
        ]);
    }

    public function test_mark_invoice_paid_reactivates_past_due_subscription(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        [$user, $organization] = $this->createAdminContext();

        $organization->subscription->update([
            'status' => Subscription::STATUS_PAST_DUE,
            'past_due_at' => now()->subDay(),
        ]);

        $invoice = SubscriptionInvoice::factory()->open()->create([
            'subscription_id' => $organization->subscription->id,
            'organization_id' => $organization->id,
            'amount_cents' => 9900,
            'due_at' => now()->subDay(),
        ]);

        $this->actingAs($platformAdmin)
            ->post("/platform/invoices/{$invoice->id}/mark-paid")
            ->assertRedirect(route('platform.invoices.index'));

        $organization->refresh();
        $organization->subscription->refresh();
        $invoice->refresh();

        $this->assertSame(Subscription::STATUS_ACTIVE, $organization->subscription->status);
        $this->assertSame(SubscriptionInvoice::STATUS_PAID, $invoice->status);
        $this->assertSame(Organization::STATUS_ACTIVE, $organization->status);
    }

    public function test_overdue_invoice_marks_subscription_past_due(): void
    {
        [$user, $organization] = $this->createAdminContext();

        $organization->subscription->update(['status' => Subscription::STATUS_ACTIVE]);

        SubscriptionInvoice::factory()->open()->create([
            'subscription_id' => $organization->subscription->id,
            'organization_id' => $organization->id,
            'due_at' => now()->subDay(),
        ]);

        $this->artisan('billing:mark-overdue-invoices')->assertSuccessful();

        $organization->subscription->refresh();

        $this->assertSame(Subscription::STATUS_PAST_DUE, $organization->subscription->status);
    }

    public function test_tenant_admin_upgrade_changes_plan_immediately(): void
    {
        [$user, $organization] = $this->createAdminContext();
        $profissionalPlan = Plan::query()->where('slug', 'profissional')->firstOrFail();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/organizations/billing/change-plan', ['plan_id' => $profissionalPlan->id])
            ->assertRedirect(route('organizations.billing.show'));

        $organization->refresh();
        $organization->subscription->refresh();

        $this->assertSame($profissionalPlan->id, $organization->subscription->plan_id);
        $this->assertSame($profissionalPlan->id, $organization->plan_id);
    }

    public function test_cancel_at_period_end_keeps_access_until_period_end(): void
    {
        [$user, $organization] = $this->createAdminContext();

        $organization->subscription->update([
            'status' => Subscription::STATUS_ACTIVE,
            'cancel_at_period_end' => true,
            'current_period_end' => now()->addWeek(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_billing_webhook_is_idempotent(): void
    {
        Queue::fake();

        [$user, $organization] = $this->createAdminContext();

        $invoice = SubscriptionInvoice::factory()->open()->create([
            'subscription_id' => $organization->subscription->id,
            'organization_id' => $organization->id,
        ]);

        $payload = [
            'event' => 'invoice.paid',
            'event_id' => 'evt_test_123',
            'invoice_id' => $invoice->id,
        ];

        $this->postJson('/webhooks/billing/manual', $payload, [
            'X-Billing-Webhook-Secret' => 'test-webhook-secret',
        ])->assertOk();

        Queue::assertPushed(ProcessBillingWebhook::class);

        (new ProcessBillingWebhook('manual', 'evt_test_123', $payload))->handle(app(MarkInvoicePaid::class));
        (new ProcessBillingWebhook('manual', 'evt_test_123', $payload))->handle(app(MarkInvoicePaid::class));

        $this->assertDatabaseCount('billing_webhook_events', 1);
        $invoice->refresh();
        $this->assertSame(SubscriptionInvoice::STATUS_PAID, $invoice->status);
    }

    public function test_trial_ending_notification_is_queued(): void
    {
        Notification::fake();

        [$user, $organization] = $this->createAdminContext();

        $organization->subscription->update([
            'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->addDays(3)->startOfDay(),
        ]);

        $this->artisan('billing:notify-trial-ending')->assertSuccessful();

        Notification::assertSentTo($user, SubscriptionTrialEndingNotification::class);
    }

    public function test_platform_mark_invoice_paid_is_audited(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        [$user, $organization] = $this->createAdminContext();

        $invoice = SubscriptionInvoice::factory()->open()->create([
            'subscription_id' => $organization->subscription->id,
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($platformAdmin)
            ->post("/platform/invoices/{$invoice->id}/mark-paid")
            ->assertRedirect();

        $this->assertDatabaseHas('platform_audit_logs', [
            'action' => 'platform.invoice.marked_paid',
            'platform_admin_user_id' => $platformAdmin->id,
        ]);
    }

    public function test_tenant_admin_can_view_billing_page(): void
    {
        [$user, $organization] = $this->createAdminContext();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/organizations/billing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organizations/Billing', false)
                ->has('invoices'));
    }

    /**
     * @return array{0: User, 1: Organization, 2: OrganizationMember}
     */
    private function createAdminContext(?Organization $organization = null): array
    {
        $organization ??= Organization::factory()->create([
            'plan_id' => Plan::query()->where('slug', 'essencial')->value('id'),
        ]);
        $user = User::factory()->create();
        $member = OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => OrganizationMember::ROLE_ADMIN,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);

        return [$user, $organization, $member];
    }
}
