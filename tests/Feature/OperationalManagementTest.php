<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Deadline;
use App\Models\InternalReminder;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperationalManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_create_and_complete_task_after_required_checklist(): void
    {
        [$admin, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        Sanctum::actingAs($admin);

        $taskId = $this->withOrganization($organization)
            ->postJson('/api/v1/tasks', [
                'client_id' => $client->id,
                'assigned_to_member_id' => $member->id,
                'title' => 'Preparar contrato',
                'priority' => Task::PRIORITY_HIGH,
                'due_at' => now()->addDays(3)->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Preparar contrato')
            ->json('data.id');

        $itemId = $this->withOrganization($organization)
            ->postJson("/api/v1/tasks/{$taskId}/checklist-items", [
                'title' => 'Conferir dados',
                'is_required' => true,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withOrganization($organization)
            ->patchJson("/api/v1/tasks/{$taskId}/complete")
            ->assertUnprocessable();

        $this->withOrganization($organization)
            ->patchJson("/api/v1/task-checklist-items/{$itemId}", [
                'is_completed' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_completed', true);

        $this->withOrganization($organization)
            ->patchJson("/api/v1/tasks/{$taskId}/complete", [
                'completion_notes' => 'Finalizado.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', Task::STATUS_COMPLETED);

        $this->assertDatabaseHas('internal_reminders', [
            'organization_id' => $organization->id,
            'type' => 'task_assigned',
        ]);
    }

    public function test_task_cannot_be_assigned_to_member_without_restricted_client_access(): void
    {
        [$admin, $organization, $adminMember] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        [, , $otherMember] = $this->createMember(OrganizationMember::ROLE_PROFESSIONAL, $organization);
        $client = $this->createClient($organization, $adminMember, Client::ACCESS_RESTRICTED);
        Sanctum::actingAs($admin);

        $this->withOrganization($organization)
            ->postJson('/api/v1/tasks', [
                'client_id' => $client->id,
                'assigned_to_member_id' => $otherMember->id,
                'title' => 'Tarefa restrita',
                'due_at' => now()->addDay()->toDateString(),
            ])
            ->assertUnprocessable();
    }

    public function test_task_template_creates_tasks_with_relative_due_dates(): void
    {
        [$admin, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        Sanctum::actingAs($admin);

        $templateId = $this->withOrganization($organization)
            ->postJson('/api/v1/task-templates', [
                'name' => 'Onboarding',
                'items' => [
                    [
                        'title' => 'Coletar documentos',
                        'due_in_days' => 2,
                        'checklist_items' => [
                            ['title' => 'CPF', 'is_required' => true],
                        ],
                    ],
                    ['title' => 'Abrir processo', 'due_in_days' => 5],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withOrganization($organization)
            ->postJson("/api/v1/task-templates/{$templateId}/create-tasks", [
                'client_id' => $client->id,
                'assigned_to_member_id' => $member->id,
                'base_date' => '2026-05-01',
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.due_at', '2026-05-03T00:00:00.000000Z');

        $this->assertDatabaseHas('tasks', [
            'organization_id' => $organization->id,
            'title' => 'Coletar documentos',
        ]);
        $this->assertDatabaseHas('task_checklist_items', [
            'organization_id' => $organization->id,
            'title' => 'CPF',
            'is_required' => true,
        ]);
    }

    public function test_deadline_requires_review_before_completion(): void
    {
        [$admin, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        Sanctum::actingAs($admin);

        $deadlineId = $this->withOrganization($organization)
            ->postJson('/api/v1/deadlines', [
                'client_id' => $client->id,
                'assigned_to_member_id' => $member->id,
                'title' => 'Prazo judicial',
                'due_at' => now()->addDays(5)->toDateString(),
                'requires_review' => true,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withOrganization($organization)
            ->patchJson("/api/v1/deadlines/{$deadlineId}/complete")
            ->assertUnprocessable();

        $this->withOrganization($organization)
            ->patchJson("/api/v1/deadlines/{$deadlineId}/request-review", [
                'review_notes' => 'Revisar peticao.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', Deadline::STATUS_REVIEW_REQUESTED);

        $this->withOrganization($organization)
            ->patchJson("/api/v1/deadlines/{$deadlineId}/approve-review")
            ->assertOk()
            ->assertJsonPath('data.status', Deadline::STATUS_REVIEW_APPROVED);

        $this->withOrganization($organization)
            ->patchJson("/api/v1/deadlines/{$deadlineId}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', Deadline::STATUS_COMPLETED);
    }

    public function test_calendar_event_records_notes_and_creates_tasks(): void
    {
        [$admin, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        Sanctum::actingAs($admin);

        $eventId = $this->withOrganization($organization)
            ->postJson('/api/v1/calendar-events', [
                'client_id' => $client->id,
                'title' => 'Reuniao de alinhamento',
                'type' => CalendarEvent::TYPE_MEETING,
                'starts_at' => now()->addDay()->toISOString(),
                'ends_at' => now()->addDay()->addHour()->toISOString(),
                'participants' => [
                    ['organization_member_id' => $member->id],
                    ['external_name' => 'Cliente', 'external_email' => 'cliente@example.com'],
                ],
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.participants')
            ->json('data.id');

        $this->withOrganization($organization)
            ->postJson("/api/v1/calendar-events/{$eventId}/notes", [
                'notes' => 'Resumo da reuniao.',
                'tasks' => [
                    [
                        'title' => 'Enviar proposta',
                        'assigned_to_member_id' => $member->id,
                        'due_at' => now()->addDays(2)->toDateString(),
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', CalendarEvent::STATUS_DONE)
            ->assertJsonPath('data.notes', 'Resumo da reuniao.');

        $this->assertDatabaseHas('tasks', [
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Enviar proposta',
        ]);
        $this->assertDatabaseHas('internal_reminders', [
            'organization_id' => $organization->id,
            'type' => 'calendar_event',
        ]);
    }

    public function test_assignment_reminders_are_idempotent_on_reassignment_to_same_member(): void
    {
        [$admin, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        Sanctum::actingAs($admin);

        $taskId = $this->withOrganization($organization)
            ->postJson('/api/v1/tasks', [
                'assigned_to_member_id' => $member->id,
                'title' => 'Revisar contrato',
                'due_at' => now()->addDay()->toDateString(),
            ])
            ->json('data.id');

        $this->withOrganization($organization)
            ->patchJson("/api/v1/tasks/{$taskId}/assign", [
                'assigned_to_member_id' => $member->id,
            ])
            ->assertOk();

        $this->assertSame(1, InternalReminder::query()->where('type', 'task_assigned')->count());
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

    private function createClient(Organization $organization, OrganizationMember $member, string $accessPolicy = Client::ACCESS_ALL_MEMBERS): Client
    {
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
            'access_policy' => $accessPolicy,
        ]);
        $client->responsibles()->attach($member->id, ['is_primary' => true]);

        return $client;
    }

    private function withOrganization(Organization $organization): self
    {
        return $this->withHeader('X-Organization-Id', (string) $organization->id);
    }
}
