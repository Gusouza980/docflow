<?php

namespace Database\Seeders;

use App\Models\OrganizationMember;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public static function permissions(): array
    {
        return [
            'organizations.view',
            'organizations.manage',
            'members.view',
            'members.manage',
            'clients.view',
            'clients.create',
            'clients.update',
            'clients.delete',
            'documents.view',
            'documents.create',
            'documents.update',
            'documents.delete',
            'document_requests.view',
            'document_requests.create',
            'document_requests.update',
            'document_requests.cancel',
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'tasks.complete',
            'deadlines.view',
            'deadlines.create',
            'deadlines.update',
            'deadlines.review',
            'calendar.view',
            'calendar.create',
            'calendar.update',
            'finance.view',
            'finance.manage',
            'audit_logs.view',
        ];
    }

    public static function rolePermissions(): array
    {
        $permissions = self::permissions();

        return [
            OrganizationMember::ROLE_ADMIN => $permissions,
            OrganizationMember::ROLE_MANAGER => array_values(array_diff($permissions, [
                'finance.manage',
            ])),
            OrganizationMember::ROLE_PROFESSIONAL => [
                'organizations.view',
                'members.view',
                'clients.view',
                'clients.create',
                'clients.update',
                'documents.view',
                'documents.create',
                'documents.update',
                'document_requests.view',
                'document_requests.create',
                'document_requests.update',
                'tasks.view',
                'tasks.create',
                'tasks.update',
                'tasks.complete',
                'deadlines.view',
                'deadlines.create',
                'deadlines.update',
                'calendar.view',
                'calendar.create',
                'calendar.update',
            ],
            OrganizationMember::ROLE_ASSISTANT => [
                'organizations.view',
                'clients.view',
                'clients.create',
                'clients.update',
                'documents.view',
                'documents.create',
                'documents.update',
                'document_requests.view',
                'document_requests.create',
                'document_requests.update',
                'tasks.view',
                'tasks.create',
                'tasks.update',
                'tasks.complete',
                'deadlines.view',
                'calendar.view',
                'calendar.create',
            ],
            OrganizationMember::ROLE_FINANCE => [
                'organizations.view',
                'clients.view',
                'documents.view',
                'document_requests.view',
                'tasks.view',
                'deadlines.view',
                'calendar.view',
                'finance.view',
                'finance.manage',
            ],
            OrganizationMember::ROLE_READONLY => [
                'organizations.view',
                'members.view',
                'clients.view',
                'documents.view',
                'document_requests.view',
                'tasks.view',
                'deadlines.view',
                'calendar.view',
                'finance.view',
            ],
        ];
    }

    /**
     * Seed roles and permissions used by organization members.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = self::permissions();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::rolePermissions() as $roleName => $permissions) {
            Role::findOrCreate($roleName, 'web')->syncPermissions($permissions);
        }

        Role::findOrCreate('super-admin', 'web')->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
