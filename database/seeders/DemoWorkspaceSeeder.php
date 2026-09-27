<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use Database\Seeders\Support\DemoWorkspaceBuilder;
use Illuminate\Database\Seeder;

class DemoWorkspaceSeeder extends Seeder
{
    /**
     * Credenciais locais (senha: password)
     *
     * Admin: platform@docflow.test
     * Escritório: admin@docflow.local
     * Essencial: admin@essencial.docflow.local
     * Profissional: admin@profissional.docflow.local
     * Trial: admin@trial.docflow.local
     * Atraso: admin@atrasado.docflow.local
     * Suspenso: admin@suspenso.docflow.local
     * Portal Escritório: portal@novaclinica.example.com
     * Portal Profissional: portal@profissional.docflow.local
     */
    public function run(): void
    {
        $builder = new DemoWorkspaceBuilder;

        foreach ($this->tenants() as $tenant) {
            $builder->seed($tenant);
        }

        $this->printCredentials();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tenants(): array
    {
        return [
            [
                'key' => 'essencial',
                'document' => '11222333000181',
                'name' => 'Escritório Essencial',
                'email' => 'contato@essencial.docflow.local',
                'phone' => '(11) 4002-1001',
                'plan_slug' => 'essencial',
                'org_status' => Organization::STATUS_ACTIVE,
                'subscription_status' => Subscription::STATUS_ACTIVE,
                'members' => [
                    ['role' => OrganizationMember::ROLE_ADMIN, 'name' => 'Admin Essencial', 'email' => 'admin@essencial.docflow.local'],
                    ['role' => OrganizationMember::ROLE_PROFESSIONAL, 'name' => 'Consultor Essencial', 'email' => 'consultor@essencial.docflow.local'],
                ],
                'seed_core' => true,
            ],
            [
                'key' => 'profissional',
                'document' => '22333444000162',
                'name' => 'Escritório Profissional',
                'email' => 'contato@profissional.docflow.local',
                'phone' => '(11) 4002-2002',
                'plan_slug' => 'profissional',
                'org_status' => Organization::STATUS_ACTIVE,
                'subscription_status' => Subscription::STATUS_ACTIVE,
                'members' => [
                    ['role' => OrganizationMember::ROLE_ADMIN, 'name' => 'Admin Profissional', 'email' => 'admin@profissional.docflow.local'],
                    ['role' => OrganizationMember::ROLE_MANAGER, 'name' => 'Marina Gestora Pro', 'email' => 'gestora@profissional.docflow.local'],
                    ['role' => OrganizationMember::ROLE_PROFESSIONAL, 'name' => 'Rafael Consultor Pro', 'email' => 'consultor@profissional.docflow.local'],
                    ['role' => OrganizationMember::ROLE_FINANCE, 'name' => 'Caio Financeiro Pro', 'email' => 'financeiro@profissional.docflow.local'],
                ],
                'seed_core' => true,
                'seed_portal' => true,
                'portal_email' => 'portal@profissional.docflow.local',
                'seed_finance' => true,
                'seed_crm' => true,
                'seed_services' => true,
                'seed_tickets' => true,
                'seed_automations' => true,
            ],
            [
                'key' => 'escritorio',
                'document' => '12345678000190',
                'name' => 'DocFlow Consultoria Integrada',
                'email' => 'contato@docflow.local',
                'phone' => '(11) 4002-8922',
                'plan_slug' => 'escritorio',
                'org_status' => Organization::STATUS_ACTIVE,
                'subscription_status' => Subscription::STATUS_ACTIVE,
                'members' => [
                    ['role' => OrganizationMember::ROLE_ADMIN, 'name' => 'Admin DocFlow', 'email' => 'admin@docflow.local'],
                    ['role' => OrganizationMember::ROLE_MANAGER, 'name' => 'Marina Gestora', 'email' => 'gestora@docflow.local'],
                    ['role' => OrganizationMember::ROLE_PROFESSIONAL, 'name' => 'Rafael Consultor', 'email' => 'consultor@docflow.local'],
                    ['role' => OrganizationMember::ROLE_ASSISTANT, 'name' => 'Bianca Assistente', 'email' => 'assistente@docflow.local'],
                    ['role' => OrganizationMember::ROLE_FINANCE, 'name' => 'Caio Financeiro', 'email' => 'financeiro@docflow.local'],
                    ['role' => OrganizationMember::ROLE_READONLY, 'name' => 'Leticia Leitura', 'email' => 'leitura@docflow.local'],
                ],
                'seed_core' => true,
                'seed_invitation' => true,
                'invite_token' => 'demo-invite-token',
                'seed_portal' => true,
                'portal_email' => 'portal@novaclinica.example.com',
                'seed_finance' => true,
                'seed_crm' => true,
                'seed_services' => true,
                'seed_tickets' => true,
                'seed_automations' => true,
                'seed_reports' => true,
                'seed_audit' => true,
                'invoice_status' => SubscriptionInvoice::STATUS_PAID,
            ],
            [
                'key' => 'trial',
                'document' => '33444555000143',
                'name' => 'Tenant em Trial',
                'email' => 'contato@trial.docflow.local',
                'phone' => '(11) 4002-3003',
                'plan_slug' => 'essencial',
                'org_status' => Organization::STATUS_ACTIVE,
                'subscription_status' => Subscription::STATUS_TRIALING,
                'trial_ends_at' => now()->addDays(14),
                'members' => [
                    ['role' => OrganizationMember::ROLE_ADMIN, 'name' => 'Admin Trial', 'email' => 'admin@trial.docflow.local'],
                ],
            ],
            [
                'key' => 'atrasado',
                'document' => '44555666000124',
                'name' => 'Tenant em Atraso',
                'email' => 'contato@atrasado.docflow.local',
                'phone' => '(11) 4002-4004',
                'plan_slug' => 'profissional',
                'org_status' => Organization::STATUS_ACTIVE,
                'subscription_status' => Subscription::STATUS_PAST_DUE,
                'past_due_at' => now()->subDays(2),
                'members' => [
                    ['role' => OrganizationMember::ROLE_ADMIN, 'name' => 'Admin Atrasado', 'email' => 'admin@atrasado.docflow.local'],
                ],
                'invoice_status' => SubscriptionInvoice::STATUS_OPEN,
            ],
            [
                'key' => 'suspenso',
                'document' => '55666777000105',
                'name' => 'Tenant Suspenso',
                'email' => 'contato@suspenso.docflow.local',
                'phone' => '(11) 4002-5005',
                'plan_slug' => 'essencial',
                'org_status' => Organization::STATUS_SUSPENDED,
                'subscription_status' => Subscription::STATUS_CANCELED,
                'canceled_at' => now()->subDays(2),
                'members' => [
                    ['role' => OrganizationMember::ROLE_ADMIN, 'name' => 'Admin Suspenso', 'email' => 'admin@suspenso.docflow.local'],
                ],
            ],
        ];
    }

    private function printCredentials(): void
    {
        $lines = [
            'Demo seed pronto. Senha de todos os logins: password',
            'Admin:              platform@docflow.test',
            'Escritório:         admin@docflow.local',
            'Essencial:          admin@essencial.docflow.local',
            'Profissional:       admin@profissional.docflow.local',
            'Trial:              admin@trial.docflow.local',
            'Atraso:             admin@atrasado.docflow.local',
            'Suspenso:           admin@suspenso.docflow.local',
            'Portal Escritório:  portal@novaclinica.example.com',
            'Portal Profissional: portal@profissional.docflow.local',
        ];

        foreach ($lines as $line) {
            $this->command?->info($line);
        }
    }
}
