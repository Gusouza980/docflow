<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import DataTable from '../../Components/Data/DataTable.vue';
import Modal from '../../Components/Overlays/Modal.vue';
import Button from '../../Components/UI/Button.vue';
import Card from '../../Components/UI/Card.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';

const props = defineProps({
    overview: { type: Object, required: true },
    productivity: { type: Object, required: true },
    documents: { type: Object, required: true },
    finance: { type: Object, default: null },
    savedFilters: { type: Array, default: () => [] },
    generatedReports: { type: Array, default: () => [] },
    schedules: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const filterModalOpen = ref(false);
const scheduleModalOpen = ref(false);
const monthlyModalOpen = ref(false);
const withEmpty = (items, label = 'Selecione') => [{ value: '', label }, ...items];
const money = (cents) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format((cents ?? 0) / 100);

const productivityColumns = [{ key: 'name', label: 'Colaborador' }, { key: 'open_tasks', label: 'Abertas' }, { key: 'completed_tasks', label: 'Concluídas' }, { key: 'overdue_tasks', label: 'Atrasadas' }];
const documentColumns = [{ key: 'title', label: 'Documento' }, { key: 'status', label: 'Status' }, { key: 'due_at', label: 'Prazo' }];
const reportColumns = [{ key: 'title', label: 'Relatório' }, { key: 'status', label: 'Status' }, { key: 'actions', label: '' }];
const scheduleColumns = [{ key: 'name', label: 'Agendamento' }, { key: 'frequency', label: 'Frequência' }, { key: 'next_run_at', label: 'Próxima execução' }];

const filterForm = useForm({
    start_date: props.filters.start_date ?? '',
    end_date: props.filters.end_date ?? '',
    client_id: props.filters.client_id ?? '',
    status: props.filters.status ?? '',
});
const saveFilterForm = useForm({ name: '', report_type: 'overview', filters: filterForm.data(), is_shared: false });
const scheduleForm = useForm({ name: '', report_type: 'overview', client_id: '', frequency: 'monthly', filters: filterForm.data(), is_active: true, next_run_at: '' });
const monthlyForm = useForm({ client_id: '', title: '', start_date: props.filters.start_date ?? '', end_date: props.filters.end_date ?? '' });

function applyFilters() {
    router.get('/reports', filterForm.data(), { preserveState: true, preserveScroll: true });
}

function saveFilter() {
    saveFilterForm.filters = filterForm.data();
    saveFilterForm.post('/reports/filters', { preserveScroll: true, onSuccess: () => filterModalOpen.value = false });
}

function saveSchedule() {
    scheduleForm.filters = filterForm.data();
    scheduleForm.post('/reports/schedules', { preserveScroll: true, onSuccess: () => scheduleModalOpen.value = false });
}

function generateMonthly() {
    monthlyForm.post('/reports/monthly', { preserveScroll: true, onSuccess: () => monthlyModalOpen.value = false });
}

function releaseReport(report) {
    useForm({}).patch(`/reports/${report.id}/release`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Relatórios" />
    <AppLayout title="Relatórios e indicadores" active-nav="reports" :breadcrumbs="[{ label: 'Relatórios' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <form class="grid gap-3 lg:grid-cols-[1fr_1fr_1fr_auto]" @submit.prevent="applyFilters">
                    <TextInput id="reports-start" v-model="filterForm.start_date" type="date" label="Início" />
                    <TextInput id="reports-end" v-model="filterForm.end_date" type="date" label="Fim" />
                    <SelectInput id="reports-client" v-model="filterForm.client_id" label="Cliente" :options="withEmpty(options.clients, 'Todos')" />
                    <div class="flex flex-wrap items-end gap-2">
                        <Button variant="secondary" @click="applyFilters">Filtrar</Button>
                        <Button variant="secondary" type="button" @click="filterModalOpen = true">Salvar filtro</Button>
                        <Button v-if="can.schedule" variant="secondary" type="button" @click="scheduleModalOpen = true">Agendar</Button>
                        <Button type="button" @click="monthlyModalOpen = true">Relatório mensal</Button>
                    </div>
                </form>
            </div>

            <div class="grid gap-4 md:grid-cols-4">
                <Card title="Clientes ativos"><p class="text-2xl font-semibold text-slate-950">{{ overview.clients.active }}</p></Card>
                <Card title="Tarefas atrasadas"><p class="text-2xl font-semibold text-red-700">{{ overview.tasks.overdue }}</p></Card>
                <Card title="Docs vencidos"><p class="text-2xl font-semibold text-amber-700">{{ overview.documents.overdue }}</p></Card>
                <Card title="Chamados abertos"><p class="text-2xl font-semibold text-slate-950">{{ overview.communication.open_tickets }}</p></Card>
            </div>

            <div v-if="overview.alerts.length" class="grid gap-2">
                <Alert v-for="alert in overview.alerts" :key="alert.type" tone="warning">{{ alert.label }}</Alert>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <DataTable :columns="productivityColumns" :rows="productivity.members" empty-title="Sem produtividade no período">
                    <template #toolbar><div class="border-b border-slate-200 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Produtividade por colaborador</h2></div></template>
                </DataTable>
                <DataTable :columns="documentColumns" :rows="documents.items" empty-title="Sem documentos pendentes">
                    <template #toolbar><div class="border-b border-slate-200 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Documentos pendentes e vencidos</h2></div></template>
                    <template #cell-title="{ row }"><div class="min-w-64"><p class="font-semibold text-slate-950">{{ row.title }}</p><p class="mt-1 text-xs text-slate-500">{{ row.client }} · {{ row.category || 'Sem categoria' }}</p></div></template>
                    <template #cell-status="{ row }"><StatusPill :status="row.status" /></template>
                </DataTable>
            </div>

            <div v-if="finance" class="grid gap-4 md:grid-cols-4">
                <Card title="A receber"><p class="text-2xl font-semibold text-slate-950">{{ money(finance.summary.open_receivables_cents) }}</p></Card>
                <Card title="Inadimplência"><p class="text-2xl font-semibold text-red-700">{{ money(finance.summary.overdue_receivables_cents) }}</p></Card>
                <Card title="Recebido"><p class="text-2xl font-semibold text-emerald-700">{{ money(finance.summary.received_cents) }}</p></Card>
                <Card title="A pagar"><p class="text-2xl font-semibold text-slate-950">{{ money(finance.summary.open_payables_cents) }}</p></Card>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <DataTable :columns="reportColumns" :rows="generatedReports" empty-title="Nenhum relatório gerado">
                    <template #toolbar><div class="border-b border-slate-200 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Relatórios mensais</h2></div></template>
                    <template #cell-title="{ row }"><div class="min-w-64"><p class="font-semibold text-slate-950">{{ row.title }}</p><p class="mt-1 text-xs text-slate-500">{{ row.client?.name ?? 'Sem cliente' }}</p></div></template>
                    <template #cell-status="{ row }"><StatusPill :status="row.status" /></template>
                    <template #cell-actions="{ row }"><div class="flex justify-end"><Button v-if="row.status !== 'released'" size="sm" variant="secondary" @click="releaseReport(row)">Liberar</Button></div></template>
                </DataTable>
                <DataTable :columns="scheduleColumns" :rows="schedules" empty-title="Nenhum agendamento">
                    <template #toolbar><div class="border-b border-slate-200 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Agendamentos planejados</h2></div></template>
                    <template #cell-name="{ row }"><div><p class="font-semibold text-slate-950">{{ row.name }}</p><p class="mt-1 text-xs text-slate-500">{{ row.report_type }} · {{ row.client?.name ?? 'Organização' }}</p></div></template>
                </DataTable>
            </div>
        </div>

        <Modal v-if="filterModalOpen" open title="Salvar filtro" @close="filterModalOpen = false">
            <form id="save-filter-form" class="grid gap-4" @submit.prevent="saveFilter">
                <TextInput id="filter-name" v-model="saveFilterForm.name" label="Nome" required :error="saveFilterForm.errors.name" />
                <SelectInput id="filter-type" v-model="saveFilterForm.report_type" label="Relatório" :options="[{ value: 'overview', label: 'Visão geral' }, { value: 'productivity', label: 'Produtividade' }, { value: 'documents', label: 'Documentos' }, { value: 'finance', label: 'Financeiro' }, { value: 'client_monthly', label: 'Mensal cliente' }]" />
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700"><input v-model="saveFilterForm.is_shared" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-300" />Compartilhar com a equipe</label>
            </form>
            <template #actions><Button type="submit" form="save-filter-form" :loading="saveFilterForm.processing">Salvar</Button></template>
        </Modal>

        <Modal v-if="scheduleModalOpen" open title="Agendar relatório" @close="scheduleModalOpen = false">
            <form id="schedule-form" class="grid gap-4" @submit.prevent="saveSchedule">
                <TextInput id="schedule-name" v-model="scheduleForm.name" label="Nome" required :error="scheduleForm.errors.name" />
                <SelectInput id="schedule-type" v-model="scheduleForm.report_type" label="Relatório" :options="[{ value: 'overview', label: 'Visão geral' }, { value: 'productivity', label: 'Produtividade' }, { value: 'documents', label: 'Documentos' }, { value: 'finance', label: 'Financeiro' }, { value: 'client_monthly', label: 'Mensal cliente' }]" />
                <div class="grid gap-4 sm:grid-cols-2"><SelectInput id="schedule-frequency" v-model="scheduleForm.frequency" label="Frequência" :options="[{ value: 'weekly', label: 'Semanal' }, { value: 'monthly', label: 'Mensal' }, { value: 'quarterly', label: 'Trimestral' }]" /><TextInput id="schedule-next" v-model="scheduleForm.next_run_at" type="date" label="Próxima execução" /></div>
            </form>
            <template #actions><Button type="submit" form="schedule-form" :loading="scheduleForm.processing">Salvar</Button></template>
        </Modal>

        <Modal v-if="monthlyModalOpen" open title="Relatório mensal do cliente" @close="monthlyModalOpen = false">
            <form id="monthly-form" class="grid gap-4" @submit.prevent="generateMonthly">
                <SelectInput id="monthly-client" v-model="monthlyForm.client_id" label="Cliente" :options="withEmpty(options.clients)" required :error="monthlyForm.errors.client_id" />
                <TextInput id="monthly-title" v-model="monthlyForm.title" label="Título" :error="monthlyForm.errors.title" />
                <div class="grid gap-4 sm:grid-cols-2"><TextInput id="monthly-start" v-model="monthlyForm.start_date" type="date" label="Início" /><TextInput id="monthly-end" v-model="monthlyForm.end_date" type="date" label="Fim" /></div>
            </form>
            <template #actions><Button type="submit" form="monthly-form" :loading="monthlyForm.processing">Gerar</Button></template>
        </Modal>
    </AppLayout>
</template>
