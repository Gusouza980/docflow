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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebDocumentManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_documents_page_lists_only_active_organization_documents(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'title' => 'Contrato visivel',
        ]);
        Document::factory()->create(['title' => 'Contrato oculto']);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/documents')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Documents/Index', false)
                ->has('documents.data', 1)
                ->where('documents.data.0.id', $document->id)
                ->where('documents.data.0.title', 'Contrato visivel'));
    }

    public function test_admin_can_create_category_upload_document_and_add_version_from_web(): void
    {
        Storage::fake('local');
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/document-categories', [
                'name' => 'Contrato social',
                'validity_days' => 365,
                'sensitivity' => DocumentCategory::SENSITIVITY_SENSITIVE,
                'is_active' => true,
            ])
            ->assertRedirect('/documents');

        $category = DocumentCategory::firstOrFail();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/documents', [
                'client_id' => $client->id,
                'document_category_id' => $category->id,
                'title' => 'Contrato assinado',
                'visibility' => Document::VISIBILITY_CONFIDENTIAL,
                'sensitivity' => Document::SENSITIVITY_SENSITIVE,
                'file' => UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $document = Document::firstOrFail();
        $firstVersion = $document->latestVersion()->firstOrFail();
        Storage::disk('local')->assertExists($firstVersion->path);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/documents/{$document->id}/versions", [
                'file' => UploadedFile::fake()->create('contrato-v2.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect("/documents/{$document->id}");

        $this->assertSame(2, $document->versions()->count());
        $this->assertNotNull($firstVersion->fresh()->replaced_at);
    }

    public function test_document_request_lifecycle_can_be_managed_from_web(): void
    {
        Storage::fake('local');
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $category = DocumentCategory::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/document-requests', [
                'client_id' => $client->id,
                'title' => 'Documentos iniciais',
                'due_at' => now()->addDays(7)->toDateString(),
                'items' => [
                    [
                        'document_category_id' => $category->id,
                        'title' => 'Contrato social',
                    ],
                ],
            ])
            ->assertRedirect();

        $documentRequest = DocumentRequest::firstOrFail();
        $item = $documentRequest->items()->firstOrFail();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/document-request-items/{$item->id}/upload", [
                'file' => UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect("/document-requests/{$documentRequest->id}");

        $this->assertSame(DocumentRequestItem::STATUS_RECEIVED, $item->fresh()->status);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->patch("/document-request-items/{$item->id}/approve")
            ->assertRedirect("/document-requests/{$documentRequest->id}");

        $this->assertSame(DocumentRequestItem::STATUS_APPROVED, $item->fresh()->status);
        $this->assertSame(DocumentRequest::STATUS_COMPLETED, $documentRequest->fresh()->status);
    }

    public function test_document_requests_page_renders_active_organization_requests(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $documentRequest = DocumentRequest::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'requested_by_user_id' => $user->id,
            'title' => 'Pendencias fiscais',
        ]);
        DocumentRequestItem::factory()->create([
            'organization_id' => $organization->id,
            'document_request_id' => $documentRequest->id,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/document-requests')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('DocumentRequests/Index', false)
                ->has('documentRequests.data', 1)
                ->where('documentRequests.data.0.title', 'Pendencias fiscais'));
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
