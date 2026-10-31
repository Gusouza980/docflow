<?php

namespace Tests\Feature;

use App\Actions\Crm\StartClientOnboarding;
use App\Actions\Documents\ReceiveDocumentRequestItemUpload;
use App\Automations\Actions\SendMessageTemplateAction;
use App\Models\AutomationRule;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\MessageTemplate;
use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateItem;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Receivable;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LinksCohesionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_uploaded_document_inherits_the_request_service(): void
    {
        Storage::fake('local');
        [$user, $organization, $member] = $this->member();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        $service = ClientService::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
        ]);
        $request = DocumentRequest::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'client_service_id' => $service->id,
            'requested_by_user_id' => $user->id,
        ]);
        $item = DocumentRequestItem::factory()->create([
            'organization_id' => $organization->id,
            'document_request_id' => $request->id,
            'title' => 'Contrato social',
        ]);

        app(ReceiveDocumentRequestItemUpload::class)->execute(
            $item,
            UploadedFile::fake()->create('contrato.pdf', 20, 'application/pdf'),
            $user->id,
        );

        $this->assertDatabaseHas('documents', [
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'client_service_id' => $service->id,
            'title' => 'Contrato social',
        ]);
    }

    public function test_onboarding_creates_a_document_request_with_items(): void
    {
        [$user, $organization, $member] = $this->member();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        $template = OnboardingTemplate::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Entrada',
            'is_active' => true,
        ]);
        OnboardingTemplateItem::factory()->create([
            'onboarding_template_id' => $template->id,
            'title' => 'Contrato social',
            'due_in_days' => 3,
        ]);

        app(StartClientOnboarding::class)->execute($client, $template, $user->id, $member->id);

        $request = DocumentRequest::query()->where('client_id', $client->id)->first();
        $this->assertNotNull($request);
        $this->assertSame('Onboarding — Entrada', $request->title);
        $this->assertDatabaseHas('document_request_items', [
            'document_request_id' => $request->id,
            'title' => 'Contrato social',
        ]);
    }

    public function test_finance_can_open_a_ticket_for_a_receivable(): void
    {
        [$user, $organization, $member] = $this->member();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        $receivable = Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'description' => 'Honorários vencidos',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/plataforma/finance/receivables/{$receivable->id}/ticket")
            ->assertRedirect();

        $ticket = Ticket::query()->where('receivable_id', $receivable->id)->first();
        $this->assertNotNull($ticket);
        $this->assertSame($client->id, $ticket->client_id);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/plataforma/finance/receivables/{$receivable->id}/ticket")
            ->assertRedirect();

        $this->assertSame(1, Ticket::query()->where('receivable_id', $receivable->id)->count());
    }

    public function test_overdue_automation_skips_when_the_portal_reminder_already_went_out_today(): void
    {
        [$user, $organization, $member] = $this->member();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        $template = MessageTemplate::factory()->create([
            'organization_id' => $organization->id,
            'created_by_user_id' => $user->id,
            'channel' => MessageTemplate::CHANNEL_EMAIL,
            'is_active' => true,
        ]);
        $receivable = Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'last_portal_reminder_at' => now(),
        ]);
        $rule = AutomationRule::factory()->create([
            'organization_id' => $organization->id,
            'trigger' => AutomationRule::TRIGGER_RECEIVABLE_OVERDUE,
        ]);

        $result = app(SendMessageTemplateAction::class)->execute($rule, $receivable, [
            'message_template_id' => $template->id,
        ]);

        $this->assertTrue($result['skipped']);
        $this->assertDatabaseCount('client_messages', 0);
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
