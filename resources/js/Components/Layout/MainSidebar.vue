<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppSidebar from './AppSidebar.vue';

defineProps({
    active: { type: String, default: null },
});

const page = usePage();

const items = computed(() => [
    { key: 'dashboard', label: 'Dashboard', icon: '▦', href: '/plataforma/dashboard' },
    { key: 'my-day', label: 'Meu dia', icon: '☀', href: '/plataforma/my-day' },
    { key: 'organizations', label: 'Organizações', icon: '◫', href: '/plataforma/organizations' },
    { key: 'team', label: 'Equipe', icon: '◎', href: '/plataforma/team' },
    ...(page.props.auth?.permissions?.can_access_crm ? [
        { key: 'leads', label: 'CRM', icon: '◈', href: '/plataforma/leads' },
        ...(page.props.auth?.permissions?.can_manage_organization ? [
            { key: 'onboarding-templates', label: 'Onboarding', icon: '▣', href: '/plataforma/onboarding-templates' },
        ] : []),
    ] : []),
    { key: 'clients', label: 'Clientes', icon: '◌', href: '/plataforma/clients' },
    ...(page.props.auth?.permissions?.can_manage_organization ? [
        { key: 'service-types', label: 'Serviços', icon: '⬡', href: '/plataforma/service-types' },
    ] : []),
    { key: 'contracts', label: 'Contratos', icon: '☰', href: '/plataforma/contracts' },
    ...(page.props.auth?.permissions?.can_access_automations ? [
        { key: 'automations', label: 'Automações', icon: '⚡', href: '/plataforma/automations' },
    ] : []),
    { key: 'documents', label: 'Documentos', icon: '□', href: '/plataforma/documents' },
    { key: 'document-requests', label: 'Solicitações', icon: '▤', href: '/plataforma/document-requests' },
    { key: 'tasks', label: 'Tarefas', icon: '✓', href: '/plataforma/tasks' },
    { key: 'task-templates', label: 'Modelos', icon: '▧', href: '/plataforma/task-templates' },
    { key: 'deadlines', label: 'Prazos', icon: '◷', href: '/plataforma/deadlines' },
    { key: 'calendar', label: 'Agenda', icon: '◇', href: '/plataforma/calendar' },
    { key: 'finance', label: 'Financeiro', icon: '$', href: '/plataforma/finance' },
    { key: 'portal', label: 'Portal', icon: '@', href: '/plataforma/portal' },
    { key: 'message-batch', label: 'Envio em lote', icon: '⇉', href: '/plataforma/messages/batch' },
    { key: 'message-templates', label: 'Msg. modelos', icon: '✉', href: '/plataforma/message-templates' },
    { key: 'announcements', label: 'Comunicados', icon: '!', href: '/plataforma/announcements' },
    { key: 'reports', label: 'Relatórios', icon: '%', href: '/plataforma/reports' },
    { key: 'audit', label: 'Auditoria', icon: '◉', href: '/plataforma/audit' },
]);
</script>

<template>
    <AppSidebar :items="items" :active="active" />
</template>
