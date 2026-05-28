<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import DataTable from '../../Components/Data/DataTable.vue';
import Pagination from '../../Components/Data/Pagination.vue';
import Modal from '../../Components/Overlays/Modal.vue';
import Button from '../../Components/UI/Button.vue';
import Badge from '../../Components/UI/Badge.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';
import TextareaInput from '../../Components/Forms/TextareaInput.vue';

const props = defineProps({
    tasks: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const createModalOpen = ref(false);
const withEmpty = (items, label = 'Todos') => [{ value: '', label }, ...items];
const statusOptions = [
    { value: '', label: 'Todos' },
    { value: 'pending', label: 'Pendente' },
    { value: 'in_progress', label: 'Em andamento' },
    { value: 'blocked', label: 'Bloqueada' },
    { value: 'completed', label: 'Concluída' },
    { value: 'cancelled', label: 'Cancelada' },
];
const priorityOptions = [
    { value: '', label: 'Todas' },
    { value: 'low', label: 'Baixa' },
    { value: 'normal', label: 'Normal' },
    { value: 'high', label: 'Alta' },
    { value: 'critical', label: 'Crítica' },
];
const strictPriorityOptions = priorityOptions.filter((option) => option.value);
const flagOptions = [
    { value: '', label: 'Todas' },
    { value: 'overdue', label: 'Atrasadas' },
    { value: 'critical', label: 'Críticas' },
];
const columns = [
    { key: 'title', label: 'Tarefa' },
    { key: 'status', label: 'Status' },
    { key: 'priority', label: 'Prioridade' },
    { key: 'assignee', label: 'Responsável' },
    { key: 'due_at', label: 'Prazo' },
    { key: 'actions', label: '' },
];

const filterForm = useForm({
    client_id: props.filters.client_id ?? '',
    assigned_to_member_id: props.filters.assigned_to_member_id ?? '',
    status: props.filters.status ?? '',
    priority: props.filters.priority ?? '',
    flag: props.filters.flag ?? '',
});

const createForm = useForm({
    client_id: '',
    assigned_to_member_id: '',
    title: '',
    description: '',
    priority: 'normal',
    due_at: '',
});

function applyFilters() {
    router.get('/tasks', filterForm.data(), { preserveState: true, preserveScroll: true });
}

function clearFilters() {
    filterForm.client_id = '';
    filterForm.assigned_to_member_id = '';
    filterForm.status = '';
    filterForm.priority = '';
    filterForm.flag = '';
    applyFilters();
}

function submitCreate() {
    createForm.post('/tasks', {
        preserveScroll: true,
        onSuccess: () => createModalOpen.value = false,
    });
}
</script>

<template>
    <Head title="Tarefas" />
    <AppLayout title="Tarefas" active-nav="tasks" :breadcrumbs="[{ label: 'Tarefas' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <DataTable :columns="columns" :rows="tasks.data" empty-title="Nenhuma tarefa encontrada">
                <template #toolbar>
                    <div class="grid gap-3 border-b border-slate-200 px-4 py-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-sm font-semibold text-slate-950">Fila operacional</h2>
                                <p class="mt-1 text-xs text-slate-500">Responsáveis, prazos, prioridade e progresso de checklist.</p>
                            </div>
                            <Button v-if="can.create" size="sm" @click="createModalOpen = true">Nova tarefa</Button>
                        </div>
                        <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-5" @submit.prevent="applyFilters">
                            <SelectInput id="task-client-filter" v-model="filterForm.client_id" label="Cliente" :options="withEmpty(options.clients)" />
                            <SelectInput id="task-assignee-filter" v-model="filterForm.assigned_to_member_id" label="Responsável" :options="withEmpty(options.members)" />
                            <SelectInput id="task-status-filter" v-model="filterForm.status" label="Status" :options="statusOptions" />
                            <SelectInput id="task-priority-filter" v-model="filterForm.priority" label="Prioridade" :options="priorityOptions" />
                            <SelectInput id="task-flag-filter" v-model="filterForm.flag" label="Visão" :options="flagOptions" />
                            <div class="flex items-end gap-2 md:col-span-2 xl:col-span-5">
                                <Button type="submit" variant="secondary">Filtrar</Button>
                                <Button variant="ghost" @click="clearFilters">Limpar</Button>
                            </div>
                        </form>
                    </div>
                </template>

                <template #cell-title="{ row }">
                    <div class="min-w-64">
                        <Link :href="row.href" class="font-semibold text-slate-950 hover:text-blue-700">{{ row.title }}</Link>
                        <p class="mt-1 text-xs text-slate-500">{{ row.client?.name ?? 'Sem cliente' }} · Checklist {{ row.checklist_progress }}</p>
                    </div>
                </template>
                <template #cell-status="{ row }"><StatusPill :status="row.status" /></template>
                <template #cell-priority="{ row }"><Badge :tone="row.priority === 'critical' ? 'danger' : 'secondary'">{{ row.priority }}</Badge></template>
                <template #cell-assignee="{ row }">{{ row.assignee?.name ?? 'Sem responsável' }}</template>
                <template #cell-due_at="{ row }"><span :class="row.is_overdue ? 'font-semibold text-red-700' : ''">{{ row.due_at }}</span></template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end">
                        <Link :href="row.href" class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-[13px] font-semibold text-slate-800 hover:bg-slate-50">Abrir</Link>
                    </div>
                </template>
            </DataTable>
            <Pagination :current-page="tasks.meta.current_page" :total-pages="tasks.meta.last_page" :per-page="tasks.meta.per_page" />
        </div>

        <Modal v-if="createModalOpen" open title="Nova tarefa" @close="createModalOpen = false">
            <form id="create-task-form" class="grid gap-4" @submit.prevent="submitCreate">
                <div class="grid gap-4 sm:grid-cols-2">
                    <SelectInput id="task-client" v-model="createForm.client_id" label="Cliente" :options="withEmpty(options.clients, 'Sem cliente')" :error="createForm.errors.client_id" />
                    <SelectInput id="task-assignee" v-model="createForm.assigned_to_member_id" label="Responsável" :options="withEmpty(options.members, 'Selecione')" :error="createForm.errors.assigned_to_member_id" />
                </div>
                <TextInput id="task-title" v-model="createForm.title" label="Título" required :error="createForm.errors.title" />
                <TextareaInput id="task-description" v-model="createForm.description" label="Descrição" :error="createForm.errors.description" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <SelectInput id="task-priority" v-model="createForm.priority" label="Prioridade" :options="strictPriorityOptions" :error="createForm.errors.priority" />
                    <TextInput id="task-due" v-model="createForm.due_at" type="date" label="Prazo" required :error="createForm.errors.due_at" />
                </div>
            </form>
            <template #actions>
                <Button type="submit" form="create-task-form" :loading="createForm.processing">Criar</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
