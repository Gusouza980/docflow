<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_create_document_category(): void
    {
        [$admin, $organization] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        Sanctum::actingAs($admin);

        $this->withOrganization($organization)
            ->postJson('/api/v1/document-categories', [
                'name' => 'Contrato Social',
                'validity_days' => 365,
                'sensitivity' => DocumentCategory::SENSITIVITY_SENSITIVE,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Contrato Social')
            ->assertJsonPath('data.validity_days', 365);

        $this->assertDatabaseHas('document_categories', [
            'organization_id' => $organization->id,
            'name' => 'Contrato Social',
        ]);
    }

    public function test_admin_can_upload_document_and_store_private_version(): void
    {
        Storage::fake('local');
        [$admin, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $category = DocumentCategory::factory()->create(['organization_id' => $organization->id]);
        Sanctum::actingAs($admin);

        $this->withOrganization($organization)
            ->postJson('/api/v1/documents', [
                'client_id' => $client->id,
                'document_category_id' => $category->id,
                'title' => 'Contrato assinado',
                'visibility' => Document::VISIBILITY_CONFIDENTIAL,
                'file' => UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Contrato assinado')
            ->assertJsonPath('data.latest_version.version_number', 1);

        $version = $client->documents()->firstOrFail()->latestVersion()->firstOrFail();
        Storage::disk('local')->assertExists($version->path);

        $this->assertStringStartsWith("organizations/{$organization->id}/documents/", $version->path);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'document.created',
        ]);
    }

    public function test_invalid_document_file_is_rejected(): void
    {
        [$admin, $organization] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        Sanctum::actingAs($admin);

        $this->withOrganization($organization)
            ->postJson('/api/v1/documents', [
                'title' => 'Arquivo invalido',
                'file' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_document_replacement_preserves_previous_version(): void
    {
        Storage::fake('local');
        [$admin, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        Sanctum::actingAs($admin);

        $documentId = $this->withOrganization($organization)
            ->postJson('/api/v1/documents', [
                'client_id' => $client->id,
                'title' => 'Contrato',
                'file' => UploadedFile::fake()->create('v1.pdf', 100, 'application/pdf'),
            ])
            ->json('data.id');

        $this->withOrganization($organization)
            ->postJson("/api/v1/documents/{$documentId}/versions", [
                'file' => UploadedFile::fake()->create('v2.pdf', 100, 'application/pdf'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.version_number', 2);

        $document = Document::findOrFail($documentId);

        $this->assertSame(2, $document->versions()->count());
        $this->assertNotNull($document->versions()->where('version_number', 1)->firstOrFail()->replaced_at);
    }

    public function test_document_view_and_download_are_authorized_and_audited(): void
    {
        Storage::fake('local');
        [$admin, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        [$professional] = $this->createMember(OrganizationMember::ROLE_PROFESSIONAL, $organization);
        $client = $this->createClient($organization, $member, Client::ACCESS_RESTRICTED);
        Sanctum::actingAs($admin);

        $documentId = $this->withOrganization($organization)
            ->postJson('/api/v1/documents', [
                'client_id' => $client->id,
                'title' => 'Sigiloso',
                'visibility' => Document::VISIBILITY_RESTRICTED,
                'file' => UploadedFile::fake()->create('sigiloso.pdf', 100, 'application/pdf'),
            ])
            ->json('data.id');

        Sanctum::actingAs($professional);
        $this->withOrganization($organization)
            ->getJson("/api/v1/documents/{$documentId}")
            ->assertForbidden();

        Sanctum::actingAs($admin);
        $this->withOrganization($organization)
            ->get("/api/v1/documents/{$documentId}/view")
            ->assertOk();

        $this->withOrganization($organization)
            ->get("/api/v1/documents/{$documentId}/download")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['organization_id' => $organization->id, 'action' => 'document.viewed']);
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $organization->id, 'action' => 'document.downloaded']);
    }

    public function test_document_request_lifecycle_with_multiple_items(): void
    {
        Storage::fake('local');
        [$admin, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $category = DocumentCategory::factory()->create(['organization_id' => $organization->id]);
        Sanctum::actingAs($admin);

        $response = $this->withOrganization($organization)
            ->postJson('/api/v1/document-requests', [
                'client_id' => $client->id,
                'title' => 'Documentos iniciais',
                'due_at' => now()->addDays(10)->toDateString(),
                'items' => [
                    ['document_category_id' => $category->id, 'title' => 'Contrato social'],
                    ['title' => 'Comprovante de endereco'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', DocumentRequest::STATUS_PENDING)
            ->assertJsonCount(2, 'data.items');

        $firstItemId = $response->json('data.items.0.id');
        $secondItemId = $response->json('data.items.1.id');

        $this->withOrganization($organization)
            ->postJson("/api/v1/document-request-items/{$firstItemId}/upload", [
                'file' => UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf'),
            ])
            ->assertOk()
            ->assertJsonPath('data.status', DocumentRequestItem::STATUS_RECEIVED);

        $this->withOrganization($organization)
            ->patchJson("/api/v1/document-request-items/{$firstItemId}/reject", [
                'rejection_reason' => 'Documento ilegivel.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', DocumentRequestItem::STATUS_REJECTED)
            ->assertJsonPath('data.rejection_reason', 'Documento ilegivel.');

        $this->withOrganization($organization)
            ->postJson("/api/v1/document-request-items/{$firstItemId}/upload", [
                'file' => UploadedFile::fake()->create('contrato-corrigido.pdf', 100, 'application/pdf'),
            ])
            ->assertOk()
            ->assertJsonPath('data.status', DocumentRequestItem::STATUS_RECEIVED);

        $this->withOrganization($organization)
            ->patchJson("/api/v1/document-request-items/{$firstItemId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', DocumentRequestItem::STATUS_APPROVED);

        $this->withOrganization($organization)
            ->postJson("/api/v1/document-request-items/{$secondItemId}/upload", [
                'file' => UploadedFile::fake()->create('comprovante.pdf', 100, 'application/pdf'),
            ])
            ->assertOk();

        $this->withOrganization($organization)
            ->patchJson("/api/v1/document-request-items/{$secondItemId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', DocumentRequestItem::STATUS_APPROVED);

        $documentRequest = DocumentRequest::firstOrFail();
        $this->assertSame(DocumentRequest::STATUS_COMPLETED, $documentRequest->fresh()->status);
    }

    public function test_document_request_can_be_cancelled(): void
    {
        [$admin, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        Sanctum::actingAs($admin);

        $requestId = $this->withOrganization($organization)
            ->postJson('/api/v1/document-requests', [
                'client_id' => $client->id,
                'title' => 'Pendencias',
                'items' => [
                    ['title' => 'Documento pendente'],
                ],
            ])
            ->json('data.id');

        $this->withOrganization($organization)
            ->patchJson("/api/v1/document-requests/{$requestId}/cancel", [
                'cancellation_reason' => 'Cliente enviou por outro canal.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', DocumentRequest::STATUS_CANCELLED)
            ->assertJsonPath('data.items.0.status', DocumentRequestItem::STATUS_CANCELLED);
    }

    public function test_readonly_member_cannot_create_documents(): void
    {
        [, $organization] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        [$readonly] = $this->createMember(OrganizationMember::ROLE_READONLY, $organization);
        Sanctum::actingAs($readonly);

        $this->withOrganization($organization)
            ->postJson('/api/v1/documents', [
                'title' => 'Nao permitido',
                'file' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
            ])
            ->assertForbidden();
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
