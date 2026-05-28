<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentVersion>
 */
class DocumentVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'document_id' => Document::factory(),
            'uploaded_by_user_id' => User::factory(),
            'version_number' => 1,
            'source' => DocumentVersion::SOURCE_INTERNAL,
            'disk' => 'local',
            'path' => 'organizations/1/documents/1/fake.pdf',
            'original_name' => 'fake.pdf',
            'stored_name' => 'fake.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'hash' => hash('sha256', fake()->uuid()),
        ];
    }
}
