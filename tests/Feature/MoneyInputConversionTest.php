<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MoneyInputConversionTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_lead_estimated_value_accepts_brazilian_reais_and_stores_cents(): void
    {
        [$user, $organization] = $this->createCrmContext();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/leads', [
                'name' => 'Lead com valor',
                'stage' => Lead::STAGE_NEW,
                'estimated_value_cents' => 'R$ 1.250,50',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('leads', [
            'organization_id' => $organization->id,
            'name' => 'Lead com valor',
            'estimated_value_cents' => 125050,
        ]);
    }

    public function test_lead_estimated_value_rejects_invalid_money_format(): void
    {
        [$user, $organization] = $this->createCrmContext();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->from('/leads')
            ->post('/leads', [
                'name' => 'Lead inválido',
                'stage' => Lead::STAGE_NEW,
                'estimated_value_cents' => 'abc',
            ])
            ->assertRedirect('/leads')
            ->assertSessionHasErrors([
                'estimated_value_cents' => 'Informe um valor válido em reais (ex.: 1.250,00).',
            ]);
    }

    public function test_receivable_amount_accepts_dot_decimal_and_stores_cents(): void
    {
        [$user, $organization, $member] = $this->createFinanceContext();
        $client = $this->createClient($organization, $member);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/finance/receivables', [
                'client_id' => $client->id,
                'description' => 'Honorários',
                'amount_cents' => '1500.50',
                'due_at' => now()->addDays(5)->toDateString(),
            ])
            ->assertRedirect('/finance');

        $this->assertDatabaseHas('receivables', [
            'organization_id' => $organization->id,
            'description' => 'Honorários',
            'amount_cents' => 150050,
        ]);
    }

    public function test_receivable_amount_rejects_more_than_two_decimal_places(): void
    {
        [$user, $organization, $member] = $this->createFinanceContext();
        $client = $this->createClient($organization, $member);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->from('/finance')
            ->post('/finance/receivables', [
                'client_id' => $client->id,
                'description' => 'Honorários',
                'amount_cents' => '10,123',
                'due_at' => now()->addDays(5)->toDateString(),
            ])
            ->assertRedirect('/finance')
            ->assertSessionHasErrors([
                'amount_cents' => 'Informe um valor válido em reais (ex.: 1.250,00).',
            ]);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function createCrmContext(): array
    {
        $planId = Plan::query()->where('slug', 'profissional')->value('id');
        $organization = Organization::factory()->create(['plan_id' => $planId]);
        $organization->subscription?->update(['plan_id' => $planId]);

        $user = User::factory()->create();
        OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => OrganizationMember::ROLE_ADMIN,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);

        return [$user, $organization];
    }

    /**
     * @return array{0: User, 1: Organization, 2: OrganizationMember}
     */
    private function createFinanceContext(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $member = OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => OrganizationMember::ROLE_FINANCE,
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
