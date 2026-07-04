<?php

return [

    'finance' => [
        'overdue_portal_reminder_cooldown_days' => (int) env('DOCFLOW_FINANCE_REMINDER_COOLDOWN_DAYS', 7),
        'delinquent_after_days' => (int) env('DOCFLOW_FINANCE_DELINQUENT_DAYS', 30),
    ],

    'default_plan_slug' => env('DOCFLOW_DEFAULT_PLAN_SLUG', 'essencial'),

    'subscription' => [
        'default_trial_days' => (int) env('DOCFLOW_SUBSCRIPTION_TRIAL_DAYS', 14),
        'grace_days' => (int) env('DOCFLOW_SUBSCRIPTION_GRACE_DAYS', 7),
    ],

    'billing' => [
        'driver' => env('DOCFLOW_BILLING_DRIVER', 'manual'),
        'invoice_due_days' => (int) env('DOCFLOW_BILLING_INVOICE_DUE_DAYS', 7),
        'asaas_api_key' => env('ASAAS_API_KEY'),
        'asaas_webhook_secret' => env('ASAAS_WEBHOOK_SECRET'),
        'webhook_secret' => env('DOCFLOW_BILLING_WEBHOOK_SECRET'),
    ],

    'plan_limits' => [
        'max_members' => ['label' => 'Membros da equipe', 'unit' => 'membros'],
        'max_clients' => ['label' => 'Clientes', 'unit' => 'clientes'],
        'max_storage_mb' => ['label' => 'Armazenamento', 'unit' => 'MB'],
        'max_portal_accesses' => ['label' => 'Acessos ao portal', 'unit' => 'acessos'],
    ],

    'plan_features' => [
        'portal' => 'Portal do cliente',
        'finance_advanced' => 'Financeiro avançado',
        'reports_scheduling' => 'Agendamento de relatórios',
        'audit' => 'Auditoria de ações',
        'automations' => 'Automações',
    ],

];
