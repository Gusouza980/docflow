<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateItem;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\Proposal;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CrmOnboardingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_essencial_plan_cannot_access_crm(): void
    {
        [$user, $organization] = $this->createContext('essencial');

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/leads')
            ->assertRedirect(route('organizations.plan.show'));
    }

    public function test_profissional_can_create_lead_and_move_stage(): void
    {
        [$user, $organization] = $this->createContext('profissional');

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/leads', [
                'name' => 'Maria Lead',
                'email' => 'maria@example.test',
                'origin' => Lead::ORIGIN_REFERRAL,
                'stage' => Lead::STAGE_NEW,
            ])
            ->assertRedirect();

        $lead = Lead::query()->where('organization_id', $organization->id)->firstOrFail();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->patch("/leads/{$lead->id}/stage", [
                'stage' => Lead::STAGE_FIRST_CONTACT,
            ])
            ->assertRedirect();

        $this->assertSame(Lead::STAGE_FIRST_CONTACT, $lead->fresh()->stage);
    }

    public function test_lost_stage_requires_reason(): void
    {
        [$user, $organization] = $this->createContext('profissional');
        $lead = Lead::factory()->create([
            'organization_id' => $organization->id,
            'owner_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->from("/leads/{$lead->id}")
            ->patch("/leads/{$lead->id}/stage", [
                'stage' => Lead::STAGE_LOST,
            ])
            ->assertSessionHasErrors('lost_reason');
    }

    public function test_convert_lead_creates_client_and_preserves_activities(): void
    {
        [$user, $organization, $member] = $this->createContext('profissional');
        $lead = Lead::factory()->create([
            'organization_id' => $organization->id,
            'owner_user_id' => $user->id,
            'name' => 'Lead Convertido',
            'email' => 'lead@example.test',
            'phone' => '11999999999',
        ]);

        LeadActivity::factory()->create([
            'lead_id' => $lead->id,
            'created_by_user_id' => $user->id,
            'body' => 'Primeiro contato realizado',
        ]);

        Proposal::factory()->create([
            'lead_id' => $lead->id,
            'title' => 'Proposta inicial',
            'status' => Proposal::STATUS_ACCEPTED,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/leads/{$lead->id}/convert")
            ->assertRedirect();

        $lead->refresh();

        $this->assertNotNull($lead->client_id);
        $this->assertSame(Lead::STAGE_WON, $lead->stage);
        $this->assertDatabaseHas('clients', [
            'id' => $lead->client_id,
            'display_name' => 'Lead Convertido',
            'organization_id' => $organization->id,
        ]);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'body' => 'Primeiro contato realizado',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get("/clients/{$lead->client_id}?tab=commercial")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Show', false)
                ->has('hub.commercial', 1));
    }

    public function test_onboarding_template_creates_tasks(): void
    {
        [$user, $organization, $member] = $this->createContext('profissional');

        $template = OnboardingTemplate::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
        ]);

        OnboardingTemplateItem::factory()->create([
            'onboarding_template_id' => $template->id,
            'title' => 'Coletar documentos',
            'due_in_days' => 2,
            'sort_order' => 0,
        ]);
        OnboardingTemplateItem::factory()->create([
            'onboarding_template_id' => $template->id,
            'title' => 'Reunião de kickoff',
            'due_in_days' => 5,
            'sort_order' => 1,
        ]);

        $lead = Lead::factory()->create([
            'organization_id' => $organization->id,
            'owner_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/leads/{$lead->id}/convert", ['start_onboarding' => true])
            ->assertRedirect();

        $this->assertSame(2, Task::query()
            ->where('organization_id', $organization->id)
            ->where('client_id', $lead->fresh()->client_id)
            ->where('title', 'like', '[Onboarding]%')
            ->count());
    }

    public function test_assistant_cannot_manage_leads(): void
    {
        [$user, $organization] = $this->createContext('profissional', OrganizationMember::ROLE_ASSISTANT);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/leads', [
                'name' => 'Lead bloqueado',
            ])
            ->assertForbidden();
    }

    public function test_lead_is_isolated_by_organization(): void
    {
        [$user, $organization] = $this->createContext('profissional');
        [$otherUser, $otherOrganization] = $this->createContext('profissional');

        $lead = Lead::factory()->create([
            'organization_id' => $otherOrganization->id,
            'owner_user_id' => $otherUser->id,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get("/leads/{$lead->id}")
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Organization, 2: OrganizationMember}
     */
    private function createContext(string $planSlug, string $role = OrganizationMember::ROLE_ADMIN): array
    {
        $planId = Plan::query()->where('slug', $planSlug)->value('id');
        $organization = Organization::factory()->create(['plan_id' => $planId]);
        $organization->subscription->update(['plan_id' => $planId]);

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
