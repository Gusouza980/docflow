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
    events: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const createModalOpen = ref(false);
const notesModalOpen = ref(false);
const selectedEvent = ref(null);
const withEmpty = (items, label = 'Todos') => [{ value: '', label }, ...items];
const typeOptions = [
    { value: '', label: 'Todos' },
    { value: 'internal', label: 'Interno' },
    { value: 'meeting', label: 'Reunião' },
    { value: 'deadline', label: 'Prazo' },
    { value: 'hearing', label: 'Audiência' },
];
const strictTypeOptions = typeOptions.filter((option) => option.value);
const columns = [
    { key: 'title', label: 'Evento' },
    { key: 'status', label: 'Status' },
    { key: 'type', label: 'Tipo' },
    { key: 'starts_at', label: 'Início' },
    { key: 'participants', label: 'Participantes' },
    { key: 'actions', label: '' },
];
const filterForm = useForm({
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    type: props.filters.type ?? '',
    client_id: props.filters.client_id ?? '',
});
const createForm = useForm({
    client_id: '',
    title: '',
    description: '',
    type: 'meeting',
    starts_at: '',
    ends_at: '',
    location: '',
    participants: [{ organization_member_id: '', external_name: '', external_email: '' }],
});
const notesForm = useForm({
    notes: '',
    tasks: [],
});

function applyFilters() {
    router.get('/calendar', filterForm.data(), { preserveState: true, preserveScroll: true });
}

function addParticipant() {
    createForm.participants.push({ organization_member_id: '', external_name: '', external_email: '' });
}

function addTaskFromNotes() {
    notesForm.tasks.push({ title: '', assigned_to_member_id: '', due_at: '', priority: 'normal' });
}

function submitCreate() {
    createForm.post('/calendar-events', { preserveScroll: true, onSuccess: () => createModalOpen.value = false });
}

function openNotes(event) {
    selectedEvent.value = event;
    notesForm.reset();
    notesModalOpen.value = true;
}

function submitNotes() {
    notesForm.post(`/calendar-events/${selectedEvent.value.id}/notes`, { preserveScroll: true, onSuccess: () => notesModalOpen.value = false });
}
</script>

<template>
    <Head title="Agenda" />
    <AppLayout title="Agenda" active-nav="calendar" :breadcrumbs="[{ label: 'Agenda' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>
            <DataTable :columns="columns" :rows="events.data" empty-title="Nenhum evento encontrado">
                <template #toolbar>
                    <div class="grid gap-3 border-b border-slate-200 px-4 py-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-sm font-semibold text-slate-950">Eventos e reuniões</h2><p class="mt-1 text-xs text-slate-500">Agenda operacional com participantes, clientes e atas.</p></div><Button v-if="can.create" size="sm" @click="createModalOpen = true">Novo evento</Button></div>
                        <form class="grid gap-3 md:grid-cols-4" @submit.prevent="applyFilters"><TextInput id="calendar-from" v-model="filterForm.from" type="date" label="De" /><TextInput id="calendar-to" v-model="filterForm.to" type="date" label="Até" /><SelectInput id="calendar-type" v-model="filterForm.type" label="Tipo" :options="typeOptions" /><SelectInput id="calendar-client" v-model="filterForm.client_id" label="Cliente" :options="withEmpty(options.clients)" /><div class="flex items-end gap-2 md:col-span-4"><Button type="submit" variant="secondary">Filtrar</Button></div></form>
                    </div>
                </template>
                <template #cell-title="{ row }"><div class="min-w-64"><p class="font-semibold text-slate-950">{{ row.title }}</p><p class="mt-1 text-xs text-slate-500">{{ row.client?.name ?? 'Sem cliente' }} · {{ row.location || 'Sem local' }}</p></div></template>
                <template #cell-status="{ row }"><StatusPill :status="row.status" /></template>
                <template #cell-type="{ row }"><Badge tone="secondary">{{ row.type }}</Badge></template>
                <template #cell-starts_at="{ row }">{{ row.starts_at }}</template>
                <template #cell-participants="{ row }">{{ row.participants.length }}</template>
                <template #cell-actions="{ row }"><div class="flex justify-end"><Button size="sm" variant="secondary" @click="openNotes(row)">Ata</Button></div></template>
            </DataTable>
            <Pagination :current-page="events.meta.current_page" :total-pages="events.meta.last_page" :per-page="events.meta.per_page" />
        </div>

        <Modal v-if="createModalOpen" open title="Novo evento" @close="createModalOpen = false">
            <form id="event-form" class="grid gap-4" @submit.prevent="submitCreate">
                <div class="grid gap-4 sm:grid-cols-2"><SelectInput id="event-client" v-model="createForm.client_id" label="Cliente" :options="withEmpty(options.clients, 'Sem cliente')" :error="createForm.errors.client_id" /><SelectInput id="event-type" v-model="createForm.type" label="Tipo" :options="strictTypeOptions" :error="createForm.errors.type" /></div>
                <TextInput id="event-title" v-model="createForm.title" label="Título" required :error="createForm.errors.title" />
                <TextareaInput id="event-description" v-model="createForm.description" label="Descrição" :error="createForm.errors.description" />
                <div class="grid gap-4 sm:grid-cols-3"><TextInput id="event-starts" v-model="createForm.starts_at" type="datetime-local" label="Início" required :error="createForm.errors.starts_at" /><TextInput id="event-ends" v-model="createForm.ends_at" type="datetime-local" label="Fim" :error="createForm.errors.ends_at" /><TextInput id="event-location" v-model="createForm.location" label="Local" :error="createForm.errors.location" /></div>
                <div class="grid gap-3"><div v-for="(participant, index) in createForm.participants" :key="index" class="grid gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-3"><SelectInput :id="`participant-member-${index}`" v-model="participant.organization_member_id" label="Membro" :options="withEmpty(options.members, 'Externo')" /><TextInput :id="`participant-name-${index}`" v-model="participant.external_name" label="Nome externo" /><TextInput :id="`participant-email-${index}`" v-model="participant.external_email" label="E-mail externo" /></div><Button variant="secondary" @click="addParticipant">Adicionar participante</Button></div>
            </form>
            <template #actions><Button type="submit" form="event-form" :loading="createForm.processing">Criar</Button></template>
        </Modal>

        <Modal v-if="notesModalOpen" open title="Registrar ata" @close="notesModalOpen = false">
            <form id="event-notes-form" class="grid gap-4" @submit.prevent="submitNotes">
                <TextareaInput id="event-notes" v-model="notesForm.notes" label="Resumo" required :error="notesForm.errors.notes" />
                <div class="grid gap-3"><div v-for="(task, index) in notesForm.tasks" :key="index" class="grid gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-3"><TextInput :id="`notes-task-title-${index}`" v-model="task.title" label="Tarefa" :error="notesForm.errors[`tasks.${index}.title`]" /><SelectInput :id="`notes-task-member-${index}`" v-model="task.assigned_to_member_id" label="Responsável" :options="withEmpty(options.members, 'Selecione')" :error="notesForm.errors[`tasks.${index}.assigned_to_member_id`]" /><TextInput :id="`notes-task-due-${index}`" v-model="task.due_at" type="date" label="Prazo" :error="notesForm.errors[`tasks.${index}.due_at`]" /></div><Button variant="secondary" @click="addTaskFromNotes">Gerar tarefa</Button></div>
            </form>
            <template #actions><Button type="submit" form="event-notes-form" :loading="notesForm.processing">Salvar ata</Button></template>
        </Modal>
    </AppLayout>
</template>
