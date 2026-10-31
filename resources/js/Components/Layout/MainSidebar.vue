<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppSidebar from './AppSidebar.vue';

defineProps({
    active: { type: String, default: null },
});

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? {});

const groups = computed(() => [
    {
        key: 'operation',
        label: 'Operação',
        items: [
            { key: 'my-day', label: 'Meu dia', icon: '☀', href: '/plataforma/my-day' },
            { key: 'clients', label: 'Clientes', icon: '◌', href: '/plataforma/clients' },
            { key: 'tasks', label: 'Tarefas', icon: '✓', href: '/plataforma/tasks' },
            { key: 'deadlines', label: 'Prazos', icon: '◷', href: '/plataforma/deadlines' },
            { key: 'calendar', label: 'Agenda', icon: '◇', href: '/plataforma/calendar' },
            { key: 'documents', label: 'Documentos', icon: '□', href: '/plataforma/documents' },
            { key: 'document-requests', label: 'Solicitações', icon: '▤', href: '/plataforma/document-requests' },
        ],
    },
    {
        key: 'commercial',
        label: 'Comercial',
        items: [
            ...(permissions.value.can_access_crm ? [
                { key: 'leads', label: 'CRM', icon: '◈', href: '/plataforma/leads' },
            ] : []),
            ...(permissions.value.can_manage_organization ? [
                { key: 'service-types', label: 'Serviços', icon: '⬡', href: '/plataforma/service-types' },
            ] : []),
            { key: 'contracts', label: 'Contratos', icon: '☰', href: '/plataforma/contracts' },
        ],
    },
    {
        key: 'communication',
        label: 'Comunicação',
        items: [
            { key: 'portal', label: 'Portal', icon: '@', href: '/plataforma/portal' },
            { key: 'message-batch', label: 'Envio em lote', icon: '⇉', href: '/plataforma/messages/batch' },
            { key: 'message-templates', label: 'Modelos', icon: '✉', href: '/plataforma/message-templates' },
        ],
    },
    {
        key: 'management',
        label: 'Gestão',
        items: [
            { key: 'dashboard', label: 'Dashboard', icon: '▦', href: '/plataforma/dashboard' },
            ...(permissions.value.can_access_finance ? [
                { key: 'finance', label: 'Financeiro', icon: '$', href: '/plataforma/finance' },
            ] : []),
            { key: 'reports', label: 'Relatórios', icon: '%', href: '/plataforma/reports' },
        ],
    },
    {
        key: 'settings',
        label: 'Configuração',
        items: [
            { key: 'organizations', label: 'Organizações', icon: '◫', href: '/plataforma/organizations' },
            { key: 'team', label: 'Equipe', icon: '◎', href: '/plataforma/team' },
            { key: 'task-templates', label: 'Modelos de tarefa', icon: '▧', href: '/plataforma/task-templates' },
            ...(permissions.value.can_access_crm && permissions.value.can_manage_organization ? [
                { key: 'onboarding-templates', label: 'Onboarding', icon: '▣', href: '/plataforma/onboarding-templates' },
            ] : []),
            ...(permissions.value.can_access_automations ? [
                { key: 'automations', label: 'Automações', icon: '⚡', href: '/plataforma/automations' },
            ] : []),
            { key: 'announcements', label: 'Comunicados', icon: '!', href: '/plataforma/announcements' },
            ...(permissions.value.can_access_audit ? [
                { key: 'audit', label: 'Auditoria', icon: '◉', href: '/plataforma/audit' },
            ] : []),
        ],
    },
]);
</script>

<template>
    <AppSidebar :groups="groups" :active="active" />
</template>
