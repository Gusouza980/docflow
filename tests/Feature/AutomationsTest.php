<?php

namespace Tests\Feature;

use App\Automations\AutomationRunner;
use App\Models\AutomationLog;
use App\Models\AutomationRule;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AutomationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_essencial_plan_cannot_access_automations(): void
    {
        [$user, $organization] = $this->createContext('essencial');

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/automations')
            ->assertRedirect(route('organizations.plan.show'));
    }

    public function test_client_created_automation_creates_tasks_from_template(): void
    {
        [$user, $organization, $member] = $this->createContext('profissional');
        $template = TaskTemplate::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
        ]);
        TaskTemplateItem::factory()->create([
            'organization_id' => $organization->id,
            'task_template_id' => $template->id,
            'title' => 'Coletar documentos',
            'due_in_days' => 2,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/automations', [
                'preset_key' => 'client_created_tasks',
                'task_template_id' => $template->id,
                'name' => 'Onboarding automático',
            ])
            ->assertRedirect();

        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);

        app(AutomationRunner::class)->dispatch(
            organization: $organization,
            trigger: AutomationRule::TRIGGER_CLIENT_CREATED,
            subject: $client,
            context: ['assigned_to_member_id' => $member->id],
        );

        $this->assertSame(1, Task::query()
            ->where('organization_id', $organization->id)
            ->where('client_id', $client->id)
            ->where('title', 'Coletar documentos')
            ->count());

        $this->assertDatabaseHas('automation_logs', [
            'organization_id' => $organization->id,
            'status' => AutomationLog::STATUS_SUCCEEDED,
        ]);
    }

    public function test_same_dedupe_key_does_not_duplicate_side_effects(): void
    {
        [$user, $organization, $member] = $this->createContext('profissional');
        $template = TaskTemplate::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
        ]);
        TaskTemplateItem::factory()->create([
            'organization_id' => $organization->id,
            'task_template_id' => $template->id,
            'title' => 'Kickoff',
        ]);

        AutomationRule::factory()->create([
            'organization_id' => $organization->id,
            'trigger' => AutomationRule::TRIGGER_CLIENT_CREATED,
            'is_active' => true,
            'actions' => [[
                'type' => AutomationRule::ACTION_CREATE_TASKS_FROM_TEMPLATE,
                'params' => ['task_template_id' => $template->id],
            ]],
        ]);

        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);

        $runner = app(AutomationRunner::class);
        $runner->dispatch($organization, AutomationRule::TRIGGER_CLIENT_CREATED, $client, ['assigned_to_member_id' => $member->id]);
        $runner->dispatch($organization, AutomationRule::TRIGGER_CLIENT_CREATED, $client, ['assigned_to_member_id' => $member->id]);

        $this->assertSame(1, Task::query()->where('client_id', $client->id)->count());
        $this->assertSame(1, AutomationLog::query()->where('organization_id', $organization->id)->count());
    }

    public function test_automation_rules_are_isolated_by_organization(): void
    {
        [$user, $organization] = $this->createContext('profissional');
        [$otherUser, $otherOrganization] = $this->createContext('profissional');

        $rule = AutomationRule::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get("/automations/{$rule->id}")
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Organization, 2: OrganizationMember}
     */
    private function createContext(string $planSlug, string $role = OrganizationMember::ROLE_ADMIN): array
    {
        $planId = Plan::query()->where('slug', $planSlug)->value('id');
        $organization = Organization::factory()->create(['plan_id' => $planId]);
        $organization->subscription?->update(['plan_id' => $planId]);

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
