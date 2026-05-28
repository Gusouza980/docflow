<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\CommunicationConsent;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortalCommunicationApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_api_creates_template_consent_and_message(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        Sanctum::actingAs($user);

        $templateId = $this->withOrganization($organization)
            ->postJson('/api/v1/message-templates', [
                'name' => 'Aviso financeiro',
                'channel' => 'email',
                'purpose' => 'billing',
                'subject' => 'Pendência',
                'body' => 'Olá {{client_name}}',
                'requires_consent' => true,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withOrganization($organization)
            ->postJson('/api/v1/messages', [
                'client_id' => $client->id,
                'message_template_id' => $templateId,
                'channel' => 'email',
                'direction' => ClientMessage::DIRECTION_OUTBOUND,
            ])
            ->assertUnprocessable();

        $this->withOrganization($organization)
            ->postJson('/api/v1/communication-consents', [
                'client_id' => $client->id,
                'channel' => 'email',
                'purpose' => 'billing',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', CommunicationConsent::STATUS_GRANTED);

        $this->withOrganization($organization)
            ->postJson('/api/v1/messages', [
                'client_id' => $client->id,
                'message_template_id' => $templateId,
                'channel' => 'email',
                'direction' => ClientMessage::DIRECTION_OUTBOUND,
                'create_ticket' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.body', "Olá {$client->display_name}");

        $this->assertDatabaseHas('message_templates', [
            'organization_id' => $organization->id,
            'name' => 'Aviso financeiro',
        ]);
        $this->assertDatabaseHas('tickets', [
            'organization_id' => $organization->id,
            'client_id' => $client->id,
        ]);
    }

    public function test_revoked_consent_blocks_next_message(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $template = MessageTemplate::factory()->create([
            'organization_id' => $organization->id,
            'created_by_user_id' => $user->id,
            'channel' => 'email',
            'purpose' => 'general',
        ]);
        $consent = CommunicationConsent::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'recorded_by_user_id' => $user->id,
            'channel' => 'email',
            'purpose' => 'general',
        ]);
        Sanctum::actingAs($user);

        $this->withOrganization($organization)
            ->patchJson("/api/v1/communication-consents/{$consent->id}/revoke")
            ->assertOk()
            ->assertJsonPath('data.status', CommunicationConsent::STATUS_REVOKED);

        $this->withOrganization($organization)
            ->postJson('/api/v1/messages', [
                'client_id' => $client->id,
                'message_template_id' => $template->id,
                'channel' => 'email',
                'direction' => ClientMessage::DIRECTION_OUTBOUND,
            ])
            ->assertUnprocessable();
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

    private function withOrganization(Organization $organization): self
    {
        return $this->withHeader('X-Organization-Id', (string) $organization->id);
    }
}
