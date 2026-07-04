<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import DataTable from '../../Components/Data/DataTable.vue';
import Card from '../../Components/UI/Card.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';
import Button from '../../Components/UI/Button.vue';
import DisplayDate from '../../Components/UI/DisplayDate.vue';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    actionOptions: { type: Array, default: () => [] },
    observability: { type: Object, default: () => ({ failed_jobs_count: 0, scheduler_runs: [] }) },
});

const page = usePage();

const filterForm = useForm({
    action: props.filters.action ?? '',
});

const logColumns = [
    { key: 'action', label: 'Ação' },
    { key: 'user', label: 'Usuário' },
    { key: 'created_at', label: 'Quando' },
];

const schedulerColumns = [
    { key: 'command', label: 'Comando' },
    { key: 'result', label: 'Resultado' },
    { key: 'duration_ms', label: 'Duração (ms)' },
    { key: 'ran_at', label: 'Executado em' },
];

function applyFilters() {
    router.get('/audit', filterForm.data(), { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head title="Auditoria" />
    <AppLayout title="Auditoria e observabilidade" active-nav="audit" :breadcrumbs="[{ label: 'Auditoria' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>

            <div class="grid gap-4 md:grid-cols-2">
                <Card title="Jobs com falha">
                    <p class="text-3xl font-semibold" :class="observability.failed_jobs_count > 0 ? 'text-red-700' : 'text-slate-950'">{{ observability.failed_jobs_count }}</p>
                    <p class="mt-2 text-sm text-slate-500">Consulte `php artisan queue:failed` para inspecionar e repetir jobs.</p>
                </Card>
                <Card title="Comandos agendados">
                    <p class="text-sm text-slate-600">Últimas execuções registradas de rotinas financeiras e de relatórios.</p>
                </Card>
            </div>

            <DataTable :columns="schedulerColumns" :rows="observability.scheduler_runs" empty-title="Nenhuma execução registrada ainda">
                <template #toolbar><div class="border-b border-slate-200 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Execuções recentes</h2></div></template>
                <template #cell-result="{ row }"><StatusPill :status="row.result === 'success' ? 'completed' : 'rejected'" /></template>
                <template #cell-ran_at="{ row }"><DisplayDate :value="row.ran_at" /></template>
            </DataTable>

            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <form class="grid gap-3 md:grid-cols-[1fr_auto]" @submit.prevent="applyFilters">
                    <SelectInput id="audit-action" v-model="filterForm.action" label="Filtrar por ação" :options="[{ value: '', label: 'Todas' }, ...actionOptions]" />
                    <div class="flex items-end"><Button variant="secondary" type="submit">Filtrar</Button></div>
                </form>
            </div>

            <DataTable :columns="logColumns" :rows="logs.data" empty-title="Nenhum evento de auditoria">
                <template #toolbar><div class="border-b border-slate-200 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Eventos recentes</h2></div></template>
                <template #cell-created_at="{ row }"><DisplayDate :value="row.created_at" /></template>
            </DataTable>
        </div>
    </AppLayout>
</template>
