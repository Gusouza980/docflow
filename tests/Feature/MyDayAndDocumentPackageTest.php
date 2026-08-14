<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientService;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\Receivable;
use App\Models\ServiceType;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MyDayAndDocumentPackageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        Carbon::setTestNow('2026-08-13 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_my_day_lists_assigned_task_pending_document_and_overdue_receivable(): void
    {
        [$user, $organization, $member] = $this->createContext();
        $client = $this->createClient($organization, $member);

        Task::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'assigned_to_member_id' => $member->id,
            'created_by_user_id' => $user->id,
            'title' => 'Revisar balancete',
            'status' => Task::STATUS_PENDING,
            'due_at' => now()->subDay()->toDateString(),
        ]);

        $request = DocumentRequest::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'requested_by_user_id' => $user->id,
        ]);
        DocumentRequestItem::factory()->create([
            'organization_id' => $organization->id,
            'document_request_id' => $request->id,
            'title' => 'Folha de pagamento',
            'status' => DocumentRequestItem::STATUS_REQUESTED,
            'due_at' => now()->toDateString(),
        ]);

        Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'description' => 'Mensalidade agosto',
            'status' => Receivable::STATUS_OPEN,
            'due_at' => now()->subDays(3)->toDateString(),
        ]);

        Ticket::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'assigned_to_member_id' => $member->id,
            'title' => 'Dúvida do cliente',
            'status' => Ticket::STATUS_NEW,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/my-day')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MyDay/Index', false)
                ->where('counts.tasks', 1)
                ->where('counts.documents', 1)
                ->where('counts.receivables', 1)
                ->where('counts.tickets', 1)
                ->where('counts.total', 4)
                ->where('can_access_finance', true)
                ->where('sections.0.items.0.title', 'Revisar balancete')
                ->where('sections.0.items.0.overdue', true)
                ->where('sections.1.items.0.title', 'Folha de pagamento')
                ->where('sections.2.items.0.title', 'Mensalidade agosto')
                ->where('sections.2.items.0.href', '/finance')
                ->where('sections.3.items.0.title', 'Dúvida do cliente'));
    }

    public function test_my_day_hides_other_organization_and_restricted_client(): void
    {
        [$admin, $organization, $adminMember] = $this->createContext();
        $finance = User::factory()->create();
        $financeMember = OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $finance->id,
            'role' => OrganizationMember::ROLE_FINANCE,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);

        $openClient = $this->createClient($organization, $financeMember);
        $restrictedClient = Client::factory()->restricted()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $adminMember->id,
        ]);
        $restrictedClient->responsibles()->attach($adminMember->id, ['is_primary' => true]);

        [$otherUser, $otherOrganization, $otherMember] = $this->createContext();
        $otherClient = $this->createClient($otherOrganization, $otherMember);

        Task::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $openClient->id,
            'assigned_to_member_id' => $financeMember->id,
            'created_by_user_id' => $finance->id,
            'title' => 'Tarefa visível',
        ]);
        Task::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $restrictedClient->id,
            'assigned_to_member_id' => $financeMember->id,
            'created_by_user_id' => $admin->id,
            'title' => 'Tarefa restrita',
        ]);
        Task::factory()->create([
            'organization_id' => $otherOrganization->id,
            'client_id' => $otherClient->id,
            'assigned_to_member_id' => $otherMember->id,
            'created_by_user_id' => $otherUser->id,
            'title' => 'Tarefa de outra org',
        ]);

        $visibleRequest = DocumentRequest::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $openClient->id,
            'requested_by_user_id' => $admin->id,
        ]);
        DocumentRequestItem::factory()->create([
            'organization_id' => $organization->id,
            'document_request_id' => $visibleRequest->id,
            'title' => 'Doc visível',
            'status' => DocumentRequestItem::STATUS_REQUESTED,
        ]);

        $hiddenRequest = DocumentRequest::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $restrictedClient->id,
            'requested_by_user_id' => $admin->id,
        ]);
        DocumentRequestItem::factory()->create([
            'organization_id' => $organization->id,
            'document_request_id' => $hiddenRequest->id,
            'title' => 'Doc restrito',
            'status' => DocumentRequestItem::STATUS_REQUESTED,
        ]);

        Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $openClient->id,
            'created_by_user_id' => $admin->id,
            'description' => 'Cobrança visível',
            'status' => Receivable::STATUS_OPEN,
            'due_at' => now()->subDay()->toDateString(),
        ]);
        Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $restrictedClient->id,
            'created_by_user_id' => $admin->id,
            'description' => 'Cobrança restrita',
            'status' => Receivable::STATUS_OPEN,
            'due_at' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($finance)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/my-day')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MyDay/Index', false)
                ->where('counts.tasks', 1)
                ->where('counts.documents', 1)
                ->where('counts.receivables', 1)
                ->where('sections.0.items.0.title', 'Tarefa visível')
                ->where('sections.1.items.0.title', 'Doc visível')
                ->where('sections.2.items.0.title', 'Cobrança visível'));
    }

    public function test_professional_does_not_see_receivables_on_my_day(): void
    {
        [$user, $organization, $member] = $this->createContext(OrganizationMember::ROLE_PROFESSIONAL);
        $client = $this->createClient($organization, $member);

        Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'description' => 'Mensalidade',
            'status' => Receivable::STATUS_OPEN,
            'due_at' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/my-day')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_access_finance', false)
                ->where('counts.receivables', 0)
                ->where('counts.total', 0));
    }

    public function test_my_day_empty_state_has_zero_items(): void
    {
        [$user, $organization] = $this->createContext();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/my-day')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MyDay/Index', false)
                ->where('counts.total', 0));
    }

    public function test_admin_can_save_monthly_document_package_on_service_type(): void
    {
        [$user, $organization] = $this->createContext();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/service-types', [
                'name' => 'Contabilidade',
                'default_billing_interval' => ServiceType::BILLING_MONTH,
                'monthly_document_items' => "DAS\nFolha\n\nExtrato",
            ])
            ->assertRedirect(route('service-types.index'));

        $type = ServiceType::query()->where('organization_id', $organization->id)->firstOrFail();
        $this->assertSame(['DAS', 'Folha', 'Extrato'], $type->monthly_document_items);
    }

    public function test_monthly_package_command_creates_items_and_is_idempotent(): void
    {
        [, $organization, $member] = $this->createContext();
        $client = $this->createClient($organization, $member);
        $type = ServiceType::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Contabilidade',
            'is_active' => true,
            'monthly_document_items' => ['DAS', 'Folha'],
        ]);
        $service = ClientService::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'service_type_id' => $type->id,
            'status' => ClientService::STATUS_ACTIVE,
        ]);

        $this->artisan('documents:generate-monthly-packages')->assertSuccessful();

        $this->assertDatabaseCount('document_requests', 1);
        $this->assertDatabaseCount('document_request_items', 2);

        $request = DocumentRequest::query()->firstOrFail();
        $this->assertSame($service->id, $request->client_service_id);
        $this->assertSame('2026-08-01', $request->billing_period->toDateString());
        $this->assertSame('Documentos de 08/2026 — Contabilidade', $request->title);
        $this->assertSame(['DAS', 'Folha'], $request->items()->orderBy('id')->pluck('title')->all());

        $this->artisan('documents:generate-monthly-packages')->assertSuccessful();

        $this->assertDatabaseCount('document_requests', 1);
        $this->assertDatabaseCount('document_request_items', 2);
    }

    public function test_monthly_package_skips_paused_service_and_empty_or_inactive_type(): void
    {
        [, $organization, $member] = $this->createContext();
        $client = $this->createClient($organization, $member);

        $pausedType = ServiceType::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
            'monthly_document_items' => ['IRPF'],
        ]);
        ClientService::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'service_type_id' => $pausedType->id,
            'status' => ClientService::STATUS_PAUSED,
        ]);

        $emptyType = ServiceType::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
            'monthly_document_items' => [],
        ]);
        ClientService::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'service_type_id' => $emptyType->id,
            'status' => ClientService::STATUS_ACTIVE,
        ]);

        $inactiveType = ServiceType::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => false,
            'monthly_document_items' => ['Balancete'],
        ]);
        ClientService::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'service_type_id' => $inactiveType->id,
            'status' => ClientService::STATUS_ACTIVE,
        ]);

        $this->artisan('documents:generate-monthly-packages')->assertSuccessful();

        $this->assertDatabaseCount('document_requests', 0);
        $this->assertDatabaseCount('document_request_items', 0);
    }

    /**
     * @return array{0: User, 1: Organization, 2: OrganizationMember}
     */
    private function createContext(string $role = OrganizationMember::ROLE_ADMIN): array
    {
        $planId = Plan::query()->where('slug', 'profissional')->value('id');
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
