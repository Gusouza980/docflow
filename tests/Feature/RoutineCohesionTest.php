<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\Deadline;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Receivable;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoutineCohesionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_my_day_includes_deadlines_assigned_to_the_member(): void
    {
        [$user, $organization, $member] = $this->member();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        Deadline::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'assigned_to_member_id' => $member->id,
            'created_by_user_id' => $user->id,
            'title' => 'Entregar DAS',
            'status' => Deadline::STATUS_PENDING,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/plataforma/my-day')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts.deadlines', 1)
                ->where('sections.4.items.0.title', 'Entregar DAS')
                ->where('sections.4.items.0.href', '/plataforma/deadlines?client_id='.$client->id));
    }

    public function test_client_hub_lists_tasks_deadlines_and_receivables(): void
    {
        [$user, $organization, $member] = $this->member();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        $client->responsibles()->sync([$member->id => ['is_primary' => true]]);
        Task::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'assigned_to_member_id' => $member->id,
            'created_by_user_id' => $user->id,
            'title' => 'Revisar contrato',
        ]);
        Deadline::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'assigned_to_member_id' => $member->id,
            'created_by_user_id' => $user->id,
            'title' => 'Prazo fiscal',
        ]);
        Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'description' => 'Honorários de outubro',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get("/plataforma/clients/{$client->id}?tab=tasks")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'tasks')
                ->where('can.finance', true)
                ->where('hub.tasks.0.title', 'Revisar contrato')
                ->where('hub.deadlines.0.title', 'Prazo fiscal')
                ->where('hub.receivables.0.description', 'Honorários de outubro'));
    }

    public function test_global_search_finds_clients_and_tasks(): void
    {
        [$user, $organization, $member] = $this->member();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
            'display_name' => 'Padaria Sol',
        ]);
        Task::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'assigned_to_member_id' => $member->id,
            'created_by_user_id' => $user->id,
            'title' => 'Conferir estoque da padaria',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/plataforma/search?q=padaria')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Index', false)
                ->where('clients.0.title', 'Padaria Sol')
                ->where('tasks.0.title', 'Conferir estoque da padaria'));
    }

    public function test_portal_ticket_links_to_the_client_hub(): void
    {
        [$user, $organization, $member] = $this->member();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        Ticket::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'title' => 'Segunda via',
        ]);
        ClientMessage::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'sent_by_user_id' => $user->id,
            'body' => 'Olá',
        ]);

        $href = '/plataforma/clients/'.$client->id.'?tab=tickets';

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/plataforma/portal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tickets.0.href', $href)
                ->where('messages.0.href', $href));
    }

    /**
     * @return array{0: User, 1: Organization, 2: OrganizationMember}
     */
    private function member(): array
    {
        $organization = Organization::factory()->create();
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
