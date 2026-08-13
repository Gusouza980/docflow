<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'essencial',
                'name' => 'Essencial',
                'description' => 'Profissionais autônomos e pequenos escritórios.',
                'price_cents' => 9900,
                'sort_order' => 1,
                'limits' => [
                    'max_members' => 3,
                    'max_clients' => 50,
                    'max_storage_mb' => 2048,
                    'max_portal_accesses' => 10,
                ],
                'features' => [
                    'portal' => false,
                    'finance_advanced' => false,
                    'reports_scheduling' => false,
                    'audit' => false,
                    'automations' => false,
                    'crm' => false,
                ],
            ],
            [
                'slug' => 'profissional',
                'name' => 'Profissional',
                'description' => 'Escritórios em crescimento com portal e relatórios.',
                'price_cents' => 24900,
                'sort_order' => 2,
                'limits' => [
                    'max_members' => 15,
                    'max_clients' => 300,
                    'max_storage_mb' => 20480,
                    'max_portal_accesses' => 100,
                ],
                'features' => [
                    'portal' => true,
                    'finance_advanced' => true,
                    'reports_scheduling' => true,
                    'audit' => false,
                    'automations' => true,
                    'crm' => true,
                ],
            ],
            [
                'slug' => 'escritorio',
                'name' => 'Escritório',
                'description' => 'Equipes maiores com auditoria e limites ampliados.',
                'price_cents' => 49900,
                'sort_order' => 3,
                'limits' => [
                    'max_members' => 50,
                    'max_clients' => Plan::LIMIT_UNLIMITED,
                    'max_storage_mb' => 102400,
                    'max_portal_accesses' => Plan::LIMIT_UNLIMITED,
                ],
                'features' => [
                    'portal' => true,
                    'finance_advanced' => true,
                    'reports_scheduling' => true,
                    'audit' => true,
                    'automations' => true,
                    'crm' => true,
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(
                ['slug' => $plan['slug']],
                [
                    ...$plan,
                    'billing_interval' => Plan::BILLING_INTERVAL_MONTH,
                    'trial_days' => 14,
                    'is_public' => true,
                    'is_active' => true,
                ],
            );
        }
    }
}
