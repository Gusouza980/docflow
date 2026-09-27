<?php

namespace Tests\Feature;

use App\Models\ClientPortalAccess;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_database_seeder_creates_admin_plans_tenants_and_billing_variants(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'platform@docflow.test')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->isPlatformAdmin());

        foreach (['essencial', 'profissional', 'escritorio'] as $slug) {
            $this->assertNotNull(Plan::query()->where('slug', $slug)->first());
        }

        $essencial = Organization::query()->where('document', '11222333000181')->first();
        $profissional = Organization::query()->where('document', '22333444000162')->first();
        $escritorio = Organization::query()->where('document', '12345678000190')->first();
        $trial = Organization::query()->where('document', '33444555000143')->first();
        $pastDue = Organization::query()->where('document', '44555666000124')->first();
        $suspended = Organization::query()->where('document', '55666777000105')->first();

        $this->assertNotNull($essencial);
        $this->assertNotNull($profissional);
        $this->assertNotNull($escritorio);
        $this->assertNotNull($trial);
        $this->assertNotNull($pastDue);
        $this->assertNotNull($suspended);

        $this->assertSame('essencial', $essencial->plan->slug);
        $this->assertSame('profissional', $profissional->plan->slug);
        $this->assertSame('escritorio', $escritorio->plan->slug);

        $this->assertSame(Subscription::STATUS_ACTIVE, $essencial->subscriptionOrFail()->status);
        $this->assertSame(Subscription::STATUS_TRIALING, $trial->subscriptionOrFail()->status);
        $this->assertSame(Subscription::STATUS_PAST_DUE, $pastDue->subscriptionOrFail()->status);
        $this->assertSame(Subscription::STATUS_CANCELED, $suspended->subscriptionOrFail()->status);
        $this->assertSame(Organization::STATUS_SUSPENDED, $suspended->status);

        $this->assertNotNull(User::query()->where('email', 'admin@docflow.local')->first());
        $this->assertNotNull(User::query()->where('email', 'admin@essencial.docflow.local')->first());
        $this->assertNotNull(User::query()->where('email', 'admin@profissional.docflow.local')->first());

        $this->assertNotNull(ClientPortalAccess::query()->where('email', 'portal@novaclinica.example.com')->first());
        $this->assertNotNull(ClientPortalAccess::query()->where('email', 'portal@profissional.docflow.local')->first());

        $paidInvoice = SubscriptionInvoice::query()
            ->where('organization_id', $escritorio->id)
            ->where('status', SubscriptionInvoice::STATUS_PAID)
            ->first();
        $openInvoice = SubscriptionInvoice::query()
            ->where('organization_id', $pastDue->id)
            ->where('status', SubscriptionInvoice::STATUS_OPEN)
            ->first();

        $this->assertNotNull($paidInvoice);
        $this->assertNotNull($openInvoice);
    }
}
