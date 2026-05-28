<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Deadline;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebOperationalManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_tasks_page_lists_active_organization_tasks(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $task = Task::factory()->create([
            'organization_id' => $organization->id,
            'assigned_to_member_id' => $member->id,
            'created_by_user_id' => $user->id,
            'title' => 'Preparar contrato',
        ]);
        Task::factory()->create(['title' => 'Tarefa oculta']);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/tasks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Index', false)
                ->has('tasks.data', 1)
                ->where('tasks.data.0.id', $task->id)
                ->where('tasks.data.0.title', 'Preparar contrato'));
    }

    public function test_admin_can_create_task_manage_checklist_and_complete_from_web(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/tasks', [
                'client_id' => $client->id,
                'assigned_to_member_id' => $member->id,
                'title' => 'Preparar contrato',
                'priority' => Task::PRIORITY_HIGH,
                'due_at' => now()->addDays(3)->toDateString(),
            ])
            ->assertRedirect();

        $task = Task::firstOrFail();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/tasks/{$task->id}/checklist-items", [
                'title' => 'Conferir dados',
                'is_required' => true,
            ])
            ->assertRedirect("/tasks/{$task->id}");

        $item = TaskChecklistItem::firstOrFail();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->patch("/tasks/{$task->id}/complete")
            ->assertRedirect("/tasks/{$task->id}");

        $this->assertNotSame(Task::STATUS_COMPLETED, $task->fresh()->status);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->patch("/task-checklist-items/{$item->id}", [
                'is_completed' => true,
            ])
            ->assertRedirect("/tasks/{$task->id}");

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->patch("/tasks/{$task->id}/complete", [
                'completion_notes' => 'Finalizado.',
            ])
            ->assertRedirect("/tasks/{$task->id}");

        $this->assertSame(Task::STATUS_COMPLETED, $task->fresh()->status);
    }

    public function test_task_template_creates_tasks_from_web(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/task-templates', [
                'name' => 'Onboarding',
                'items' => [
                    ['title' => 'Coletar documentos', 'due_in_days' => 2],
                    ['title' => 'Abrir processo', 'due_in_days' => 5],
                ],
            ])
            ->assertRedirect('/task-templates');

        $template = TaskTemplate::firstOrFail();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/task-templates/{$template->id}/create-tasks", [
                'client_id' => $client->id,
                'assigned_to_member_id' => $member->id,
                'base_date' => '2026-05-01',
            ])
            ->assertRedirect('/tasks');

        $this->assertSame(2, Task::query()->count());
        $this->assertDatabaseHas('tasks', [
            'organization_id' => $organization->id,
            'title' => 'Coletar documentos',
            'due_at' => '2026-05-03 00:00:00',
        ]);
    }

    public function test_admin_can_update_task_template_and_replace_items_from_web(): void
    {
        [$user, $organization] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $template = TaskTemplate::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Onboarding antigo',
            'priority' => Task::PRIORITY_NORMAL,
        ]);
        TaskTemplateItem::factory()->create([
            'organization_id' => $organization->id,
            'task_template_id' => $template->id,
            'title' => 'Item antigo',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->patch("/task-templates/{$template->id}", [
                'name' => 'Onboarding atualizado',
                'description' => 'Fluxo revisado',
                'priority' => Task::PRIORITY_HIGH,
                'is_active' => false,
                'items' => [
                    [
                        'title' => 'Coletar documentos',
                        'description' => 'Documentos iniciais',
                        'due_in_days' => 2,
                        'priority' => Task::PRIORITY_HIGH,
                    ],
                    [
                        'title' => 'Abrir processo',
                        'due_in_days' => 5,
                        'priority' => Task::PRIORITY_NORMAL,
                    ],
                ],
            ])
            ->assertRedirect('/task-templates');

        $this->assertDatabaseHas('task_templates', [
            'id' => $template->id,
            'name' => 'Onboarding atualizado',
            'priority' => Task::PRIORITY_HIGH,
            'is_active' => false,
        ]);
        $this->assertDatabaseMissing('task_template_items', [
            'task_template_id' => $template->id,
            'title' => 'Item antigo',
        ]);
        $this->assertDatabaseHas('task_template_items', [
            'task_template_id' => $template->id,
            'title' => 'Coletar documentos',
            'due_in_days' => 2,
        ]);
        $this->assertSame(2, $template->items()->count());
    }

    public function test_deadline_review_and_completion_flow_from_web(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/deadlines', [
                'client_id' => $client->id,
                'assigned_to_member_id' => $member->id,
                'title' => 'Prazo judicial',
                'due_at' => now()->addDays(5)->toDateString(),
                'requires_review' => true,
            ])
            ->assertRedirect('/deadlines');

        $deadline = Deadline::firstOrFail();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->patch("/deadlines/{$deadline->id}/complete")
            ->assertRedirect('/deadlines');

        $this->assertNotSame(Deadline::STATUS_COMPLETED, $deadline->fresh()->status);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->patch("/deadlines/{$deadline->id}/request-review", [
                'review_notes' => 'Revisar petição.',
            ])
            ->assertRedirect('/deadlines');

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->patch("/deadlines/{$deadline->id}/approve-review")
            ->assertRedirect('/deadlines');

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->patch("/deadlines/{$deadline->id}/complete")
            ->assertRedirect('/deadlines');

        $this->assertSame(Deadline::STATUS_COMPLETED, $deadline->fresh()->status);
    }

    public function test_calendar_event_notes_create_tasks_from_web(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/calendar-events', [
                'client_id' => $client->id,
                'title' => 'Reunião de alinhamento',
                'type' => CalendarEvent::TYPE_MEETING,
                'starts_at' => now()->addDay()->toISOString(),
                'ends_at' => now()->addDay()->addHour()->toISOString(),
                'participants' => [
                    ['organization_member_id' => $member->id],
                ],
            ])
            ->assertRedirect('/calendar');

        $event = CalendarEvent::firstOrFail();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/calendar-events/{$event->id}/notes", [
                'notes' => 'Resumo da reunião.',
                'tasks' => [
                    [
                        'title' => 'Enviar proposta',
                        'assigned_to_member_id' => $member->id,
                        'due_at' => now()->addDays(2)->toDateString(),
                    ],
                ],
            ])
            ->assertRedirect('/calendar');

        $this->assertSame(CalendarEvent::STATUS_DONE, $event->fresh()->status);
        $this->assertDatabaseHas('tasks', [
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Enviar proposta',
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
