<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppSidebar from './AppSidebar.vue';

defineProps({
    active: { type: String, default: null },
});

const page = usePage();

const items = computed(() => [
    { key: 'dashboard', label: 'Dashboard', icon: '▦', href: '/dashboard' },
    { key: 'organizations', label: 'Organizações', icon: '◫', href: '/organizations' },
    { key: 'team', label: 'Equipe', icon: '◎', href: '/team' },
    ...(page.props.auth?.permissions?.can_access_crm ? [
        { key: 'leads', label: 'CRM', icon: '◈', href: '/leads' },
        ...(page.props.auth?.permissions?.can_manage_organization ? [
            { key: 'onboarding-templates', label: 'Onboarding', icon: '▣', href: '/onboarding-templates' },
        ] : []),
    ] : []),
    { key: 'clients', label: 'Clientes', icon: '◌', href: '/clients' },
    ...(page.props.auth?.permissions?.can_manage_organization ? [
        { key: 'service-types', label: 'Serviços', icon: '⬡', href: '/service-types' },
    ] : []),
    { key: 'contracts', label: 'Contratos', icon: '☰', href: '/contracts' },
    ...(page.props.auth?.permissions?.can_access_automations ? [
        { key: 'automations', label: 'Automações', icon: '⚡', href: '/automations' },
    ] : []),
    { key: 'documents', label: 'Documentos', icon: '□', href: '/documents' },
    { key: 'document-requests', label: 'Solicitações', icon: '▤', href: '/document-requests' },
    { key: 'tasks', label: 'Tarefas', icon: '✓', href: '/tasks' },
    { key: 'task-templates', label: 'Modelos', icon: '▧', href: '/task-templates' },
    { key: 'deadlines', label: 'Prazos', icon: '◷', href: '/deadlines' },
    { key: 'calendar', label: 'Agenda', icon: '◇', href: '/calendar' },
    { key: 'finance', label: 'Financeiro', icon: '$', href: '/finance' },
    { key: 'portal', label: 'Portal', icon: '@', href: '/portal' },
    { key: 'message-templates', label: 'Msg. modelos', icon: '✉', href: '/message-templates' },
    { key: 'announcements', label: 'Comunicados', icon: '!', href: '/announcements' },
    { key: 'reports', label: 'Relatórios', icon: '%', href: '/reports' },
    { key: 'audit', label: 'Auditoria', icon: '◉', href: '/audit' },
]);
</script>

<template>
    <AppSidebar :items="items" :active="active" />
</template>
