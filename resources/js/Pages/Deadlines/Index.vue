<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
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
    deadlines: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const createModalOpen = ref(false);
const reviewModalOpen = ref(false);
const completeModalOpen = ref(false);
const selectedDeadline = ref(null);
const withEmpty = (items, label = 'Todos') => [{ value: '', label }, ...items];
const statusOptions = [
    { value: '', label: 'Todos' },
    { value: 'pending', label: 'Pendente' },
    { value: 'review_requested', label: 'Em revisão' },
    { value: 'review_approved', label: 'Revisão aprovada' },
    { value: 'completed', label: 'Concluído' },
    { value: 'cancelled', label: 'Cancelado' },
];
const urgencyOptions = [
    { value: 'low', label: 'Baixa' },
    { value: 'normal', label: 'Normal' },
    { value: 'high', label: 'Alta' },
    { value: 'critical', label: 'Crítica' },
];
const columns = [
    { key: 'title', label: 'Prazo' },
    { key: 'status', label: 'Status' },
    { key: 'urgency', label: 'Urgência' },
    { key: 'assignee', label: 'Responsável' },
    { key: 'due_at', label: 'Data' },
    { key: 'actions', label: '' },
];

const filterForm = useForm({
    client_id: props.filters.client_id ?? '',
    assigned_to_member_id: props.filters.assigned_to_member_id ?? '',
    status: props.filters.status ?? '',
    overdue: props.filters.overdue ? '1' : '',
});
const createForm = useForm({
    client_id: '',
    assigned_to_member_id: '',
    title: '',
    description: '',
    type: 'general',
    urgency: 'normal',
    due_at: '',
    requires_review: false,
});
const reviewForm = useForm({ review_notes: '' });
const completeForm = useForm({ completion_notes: '' });

function applyFilters() {
    router.get('/deadlines', filterForm.data(), { preserveState: true, preserveScroll: true });
}

function clearFilters() {
    filterForm.client_id = '';
    filterForm.assigned_to_member_id = '';
    filterForm.status = '';
    filterForm.overdue = '';
    applyFilters();
}

function submitCreate() {
    createForm.post('/deadlines', { preserveScroll: true, onSuccess: () => createModalOpen.value = false });
}

function requestReview(deadline) {
    selectedDeadline.value = deadline;
    reviewForm.reset();
    reviewModalOpen.value = true;
}

function submitReview() {
    reviewForm.patch(`/deadlines/${selectedDeadline.value.id}/request-review`, { preserveScroll: true, onSuccess: () => reviewModalOpen.value = false });
}

function approveReview(deadline) {
    useForm({}).patch(`/deadlines/${deadline.id}/approve-review`, { preserveScroll: true });
}

function complete(deadline) {
    selectedDeadline.value = deadline;
    completeForm.reset();
    completeModalOpen.value = true;
}

function submitComplete() {
    completeForm.patch(`/deadlines/${selectedDeadline.value.id}/complete`, { preserveScroll: true, onSuccess: () => completeModalOpen.value = false });
}
</script>

<template>
    <Head title="Prazos" />
    <AppLayout title="Prazos" active-nav="deadlines" :breadcrumbs="[{ label: 'Prazos' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <DataTable :columns="columns" :rows="deadlines.data" empty-title="Nenhum prazo encontrado">
                <template #toolbar>
                    <div class="grid gap-3 border-b border-slate-200 px-4 py-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div><h2 class="text-sm font-semibold text-slate-950">Prazos importantes</h2><p class="mt-1 text-xs text-slate-500">Controle revisão, urgência e conclusão.</p></div>
                            <Button v-if="can.create" size="sm" @click="createModalOpen = true">Novo prazo</Button>
                        </div>
                        <form class="grid gap-3 md:grid-cols-4" @submit.prevent="applyFilters">
                            <SelectInput id="deadline-client-filter" v-model="filterForm.client_id" label="Cliente" :options="withEmpty(options.clients)" />
                            <SelectInput id="deadline-member-filter" v-model="filterForm.assigned_to_member_id" label="Responsável" :options="withEmpty(options.members)" />
                            <SelectInput id="deadline-status-filter" v-model="filterForm.status" label="Status" :options="statusOptions" />
                            <SelectInput id="deadline-overdue-filter" v-model="filterForm.overdue" label="Visão" :options="[{ value: '', label: 'Todos' }, { value: '1', label: 'Atrasados' }]" />
                            <div class="flex items-end gap-2 md:col-span-4"><Button type="submit" variant="secondary">Filtrar</Button><Button variant="ghost" @click="clearFilters">Limpar</Button></div>
                        </form>
                    </div>
                </template>
                <template #cell-title="{ row }"><div class="min-w-64"><p class="font-semibold text-slate-950">{{ row.title }}</p><p class="mt-1 text-xs text-slate-500">{{ row.client?.name ?? 'Sem cliente' }} · {{ row.type }}</p></div></template>
                <template #cell-status="{ row }"><StatusPill :status="row.status" /></template>
                <template #cell-urgency="{ row }"><Badge :tone="row.urgency === 'critical' ? 'danger' : 'secondary'">{{ row.urgency }}</Badge></template>
                <template #cell-assignee="{ row }">{{ row.assignee?.name ?? 'Sem responsável' }}</template>
                <template #cell-actions="{ row }"><div class="flex justify-end gap-2"><Button size="sm" variant="secondary" @click="requestReview(row)">Revisar</Button><Button v-if="row.status === 'review_requested'" size="sm" variant="secondary" @click="approveReview(row)">Aprovar</Button><Button size="sm" @click="complete(row)">Concluir</Button></div></template>
            </DataTable>
            <Pagination :current-page="deadlines.meta.current_page" :total-pages="deadlines.meta.last_page" :per-page="deadlines.meta.per_page" />
        </div>

        <Modal v-if="createModalOpen" open title="Novo prazo" @close="createModalOpen = false">
            <form id="create-deadline-form" class="grid gap-4" @submit.prevent="submitCreate">
                <div class="grid gap-4 sm:grid-cols-2"><SelectInput id="deadline-client" v-model="createForm.client_id" label="Cliente" :options="withEmpty(options.clients, 'Sem cliente')" :error="createForm.errors.client_id" /><SelectInput id="deadline-assignee" v-model="createForm.assigned_to_member_id" label="Responsável" :options="withEmpty(options.members, 'Selecione')" :error="createForm.errors.assigned_to_member_id" /></div>
                <TextInput id="deadline-title" v-model="createForm.title" label="Título" required :error="createForm.errors.title" />
                <TextareaInput id="deadline-description" v-model="createForm.description" label="Descrição" :error="createForm.errors.description" />
                <div class="grid gap-4 sm:grid-cols-3"><TextInput id="deadline-type" v-model="createForm.type" label="Tipo" :error="createForm.errors.type" /><SelectInput id="deadline-urgency" v-model="createForm.urgency" label="Urgência" :options="urgencyOptions" :error="createForm.errors.urgency" /><TextInput id="deadline-due" v-model="createForm.due_at" type="date" label="Data" required :error="createForm.errors.due_at" /></div>
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700"><input v-model="createForm.requires_review" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-300" />Exige revisão</label>
            </form>
            <template #actions><Button type="submit" form="create-deadline-form" :loading="createForm.processing">Criar</Button></template>
        </Modal>

        <Modal v-if="reviewModalOpen" open title="Solicitar revisão" @close="reviewModalOpen = false"><form id="deadline-review-form" class="grid gap-4" @submit.prevent="submitReview"><TextareaInput id="deadline-review-notes" v-model="reviewForm.review_notes" label="Notas de revisão" :error="reviewForm.errors.review_notes" /></form><template #actions><Button type="submit" form="deadline-review-form" :loading="reviewForm.processing">Solicitar</Button></template></Modal>
        <Modal v-if="completeModalOpen" open title="Concluir prazo" @close="completeModalOpen = false"><form id="deadline-complete-form" class="grid gap-4" @submit.prevent="submitComplete"><TextareaInput id="deadline-complete-notes" v-model="completeForm.completion_notes" label="Notas de conclusão" :error="completeForm.errors.completion_notes" /></form><template #actions><Button type="submit" form="deadline-complete-form" :loading="completeForm.processing">Concluir</Button></template></Modal>
    </AppLayout>
</template>
