<script setup>
import AppSidebar from './AppSidebar.vue';

defineProps({
    active: { type: String, default: null },
});

const items = [
    { key: 'dashboard', label: 'Dashboard', icon: '▦', href: '/dashboard' },
    { key: 'organizations', label: 'Organizações', icon: '◫', href: '/organizations' },
    { key: 'team', label: 'Equipe', icon: '◎', href: '/team' },
    { key: 'clients', label: 'Clientes', icon: '◌', href: '/clients' },
    { key: 'documents', label: 'Documentos', icon: '□', href: '/documents' },
    { key: 'document-requests', label: 'Solicitações', icon: '▤', href: '/document-requests' },
    { key: 'tasks', label: 'Tarefas', icon: '✓', href: '/tasks' },
    { key: 'task-templates', label: 'Modelos', icon: '▧', href: '/task-templates' },
    { key: 'deadlines', label: 'Prazos', icon: '◷', href: '/deadlines' },
    { key: 'calendar', label: 'Agenda', icon: '◇', href: '/calendar' },
    { key: 'finance', label: 'Financeiro', icon: '$', href: '/finance' },
    { key: 'portal', label: 'Portal', icon: '@', href: '/portal' },
    { key: 'reports', label: 'Relatórios', icon: '%', href: '/reports' },
];
</script>

<template>
    <AppSidebar :items="items" :active="active" />
</template>
