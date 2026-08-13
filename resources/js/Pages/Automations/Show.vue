<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Card from '../../Components/UI/Card.vue';
import Button from '../../Components/UI/Button.vue';
import Badge from '../../Components/UI/Badge.vue';
import DataTable from '../../Components/Data/DataTable.vue';

const props = defineProps({
    rule: { type: Object, required: true },
    logs: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const actionForm = useForm({});

const columns = [
    { key: 'ran_at', label: 'Quando' },
    { key: 'status', label: 'Status' },
    { key: 'dedupe_key', label: 'Dedupe' },
];

function pause() {
    actionForm.post(`/automations/${props.rule.id}/pause`, { preserveScroll: true });
}

function resume() {
    actionForm.post(`/automations/${props.rule.id}/resume`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="rule.name" />
    <AppLayout
        :title="rule.name"
        active-nav="automations"
        :breadcrumbs="[{ label: 'Automações', href: '/automations' }, { label: rule.name }]"
    >
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <Card :title="rule.name">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm text-slate-600">Gatilho: {{ rule.trigger_label }}</p>
                        <p class="mt-1 text-xs text-slate-500">Preset: {{ rule.preset_key || 'custom' }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge :tone="rule.is_active ? 'success' : 'secondary'">{{ rule.is_active ? 'Ativa' : 'Pausada' }}</Badge>
                        <Button v-if="can.manage && rule.is_active" size="sm" variant="secondary" :disabled="actionForm.processing" @click="pause">Pausar</Button>
                        <Button v-if="can.manage && !rule.is_active" size="sm" :disabled="actionForm.processing" @click="resume">Reativar</Button>
                    </div>
                </div>
            </Card>

            <Card title="Ações">
                <pre class="overflow-x-auto rounded bg-slate-50 p-3 text-xs text-slate-700">{{ JSON.stringify(rule.actions, null, 2) }}</pre>
            </Card>

            <DataTable :columns="columns" :rows="logs" empty-title="Nenhuma execução registrada">
                <template #toolbar>
                    <div class="border-b border-slate-200 px-4 py-3">
                        <h2 class="text-sm font-semibold text-slate-950">Últimas execuções</h2>
                    </div>
                </template>
                <template #cell-status="{ row }">
                    <Badge :tone="row.status === 'succeeded' ? 'success' : (row.status === 'failed' ? 'danger' : 'secondary')">{{ row.status }}</Badge>
                </template>
                <template #cell-dedupe_key="{ row }">
                    <code class="text-xs text-slate-600">{{ row.dedupe_key }}</code>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
