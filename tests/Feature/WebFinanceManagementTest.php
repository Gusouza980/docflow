<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\FinancialCategory;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Payable;
use App\Models\Receivable;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebFinanceManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_finance_page_lists_active_organization_receivables_and_payables(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_FINANCE);
        $client = $this->createClient($organization, $member);

        Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'description' => 'Mensalidade',
        ]);
        Payable::factory()->create([
            'organization_id' => $organization->id,
            'created_by_user_id' => $user->id,
            'description' => 'Despesa operacional',
        ]);
        Receivable::factory()->create(['description' => 'Cobrança oculta']);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/finance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Finance/Index', false)
                ->has('receivables.data', 1)
                ->where('receivables.data.0.description', 'Mensalidade')
                ->has('payables.data', 1)
                ->where('payables.data.0.description', 'Despesa operacional'));
    }

    public function test_finance_member_can_create_category_receivable_and_record_partial_and_total_payment(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_FINANCE);
        $client = $this->createClient($organization, $member);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/finance/categories', [
                'name' => 'Honorários',
                'type' => FinancialCategory::TYPE_INCOME,
            ])
            ->assertRedirect('/finance');

        $category = FinancialCategory::firstOrFail();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/finance/receivables', [
                'client_id' => $client->id,
                'financial_category_id' => $category->id,
                'description' => 'Honorários mensais',
                'amount_cents' => 100000,
                'due_at' => now()->addDays(5)->toDateString(),
            ])
            ->assertRedirect('/finance');

        $receivable = Receivable::firstOrFail();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/finance/receivables/{$receivable->id}/payments", [
                'amount_cents' => 40000,
                'paid_at' => now()->toDateString(),
            ])
            ->assertRedirect('/finance');

        $this->assertSame(Receivable::STATUS_PARTIAL, $receivable->fresh()->status);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/finance/receivables/{$receivable->id}/payments", [
                'amount_cents' => 60000,
                'paid_at' => now()->toDateString(),
            ])
            ->assertRedirect('/finance');

        $this->assertSame(Receivable::STATUS_PAID, $receivable->fresh()->status);
        $this->assertSame(100000, $receivable->fresh()->paid_amount_cents);
    }

    public function test_finance_member_can_create_and_pay_payable(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_FINANCE);
        $client = $this->createClient($organization, $member);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/finance/payables', [
                'client_id' => $client->id,
                'description' => 'Custas',
                'vendor_name' => 'Tribunal',
                'amount_cents' => 25000,
                'due_at' => now()->addDay()->toDateString(),
                'is_reimbursable' => true,
            ])
            ->assertRedirect('/finance');

        $payable = Payable::firstOrFail();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/finance/payables/{$payable->id}/payments", [
                'amount_cents' => 25000,
                'paid_at' => now()->toDateString(),
            ])
            ->assertRedirect('/finance');

        $this->assertSame(Payable::STATUS_PAID, $payable->fresh()->status);
        $this->assertTrue($payable->fresh()->is_reimbursable);
    }

    public function test_assistant_cannot_access_finance_page(): void
    {
        [$user, $organization] = $this->createMember(OrganizationMember::ROLE_ASSISTANT);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/finance')
            ->assertForbidden();
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
