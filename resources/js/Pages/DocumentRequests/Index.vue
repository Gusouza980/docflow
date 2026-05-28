<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import DataTable from '../../Components/Data/DataTable.vue';
import Pagination from '../../Components/Data/Pagination.vue';
import Modal from '../../Components/Overlays/Modal.vue';
import Button from '../../Components/UI/Button.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';
import TextareaInput from '../../Components/Forms/TextareaInput.vue';

const props = defineProps({
    documentRequests: { type: Object, required: true },
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
    { value: 'completed', label: 'Concluída' },
    { value: 'cancelled', label: 'Cancelada' },
];
const booleanOptions = [
    { value: '', label: 'Todas' },
    { value: '1', label: 'Atrasadas' },
];
const columns = [
    { key: 'title', label: 'Solicitação' },
    { key: 'status', label: 'Status' },
    { key: 'client', label: 'Cliente' },
    { key: 'progress', label: 'Progresso' },
    { key: 'due_at', label: 'Prazo' },
    { key: 'actions', label: '' },
];

const filterForm = useForm({
    client_id: props.filters.client_id ?? '',
    status: props.filters.status ?? '',
    overdue: props.filters.overdue ? '1' : '',
});

const createForm = useForm({
    client_id: '',
    title: '',
    instructions: '',
    due_at: '',
    items: [
        { document_category_id: '', title: '', instructions: '', due_at: '' },
    ],
});

function applyFilters() {
    router.get('/document-requests', filterForm.data(), { preserveState: true, preserveScroll: true });
}

function clearFilters() {
    filterForm.client_id = '';
    filterForm.status = '';
    filterForm.overdue = '';
    applyFilters();
}

function addItem() {
    createForm.items.push({ document_category_id: '', title: '', instructions: '', due_at: '' });
}

function removeItem(index) {
    if (createForm.items.length === 1) {
        return;
    }

    createForm.items.splice(index, 1);
}

function submitCreate() {
    createForm.post('/document-requests', {
        preserveScroll: true,
        onSuccess: () => createModalOpen.value = false,
    });
}
</script>

<template>
    <Head title="Solicitações de documentos" />
    <AppLayout title="Solicitações" active-nav="document-requests" :breadcrumbs="[{ label: 'Solicitações' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <DataTable :columns="columns" :rows="documentRequests.data" empty-title="Nenhuma solicitação encontrada">
                <template #toolbar>
                    <div class="grid gap-3 border-b border-slate-200 px-4 py-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-sm font-semibold text-slate-950">Solicitações aos clientes</h2>
                                <p class="mt-1 text-xs text-slate-500">Acompanhe pendências, recebimentos, aprovações e recusas.</p>
                            </div>
                            <Button v-if="can.create" size="sm" @click="createModalOpen = true">Nova solicitação</Button>
                        </div>
                        <form class="grid gap-3 md:grid-cols-3" @submit.prevent="applyFilters">
                            <SelectInput id="request-client" v-model="filterForm.client_id" label="Cliente" :options="withEmpty(options.clients)" />
                            <SelectInput id="request-status" v-model="filterForm.status" label="Status" :options="statusOptions" />
                            <SelectInput id="request-overdue" v-model="filterForm.overdue" label="Prazo" :options="booleanOptions" />
                            <div class="flex items-end gap-2 md:col-span-3">
                                <Button type="submit" variant="secondary">Filtrar</Button>
                                <Button variant="ghost" @click="clearFilters">Limpar</Button>
                            </div>
                        </form>
                    </div>
                </template>

                <template #cell-title="{ row }">
                    <Link :href="row.href" class="font-semibold text-slate-950 hover:text-blue-700">{{ row.title }}</Link>
                    <p class="mt-1 text-xs text-slate-500">{{ row.instructions || 'Sem instruções' }}</p>
                </template>
                <template #cell-status="{ row }"><StatusPill :status="row.status" /></template>
                <template #cell-client="{ row }">{{ row.client.name }}</template>
                <template #cell-progress="{ row }">{{ row.approved_items_count }}/{{ row.items_count }} aprovados</template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end">
                        <Link :href="row.href" class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-[13px] font-semibold text-slate-800 hover:bg-slate-50">Abrir</Link>
                    </div>
                </template>
            </DataTable>

            <Pagination :current-page="documentRequests.meta.current_page" :total-pages="documentRequests.meta.last_page" :per-page="documentRequests.meta.per_page" />
        </div>

        <Modal v-if="createModalOpen" open title="Nova solicitação" description="Defina os documentos esperados do cliente." @close="createModalOpen = false">
            <form id="create-document-request-form" class="grid gap-4" @submit.prevent="submitCreate">
                <SelectInput id="create-request-client" v-model="createForm.client_id" label="Cliente" :options="withEmpty(options.clients, 'Selecione')" :error="createForm.errors.client_id" />
                <TextInput id="create-request-title" v-model="createForm.title" label="Título" required :error="createForm.errors.title" />
                <TextareaInput id="create-request-instructions" v-model="createForm.instructions" label="Instruções gerais" :error="createForm.errors.instructions" />
                <TextInput id="create-request-due" v-model="createForm.due_at" type="date" label="Prazo geral" :error="createForm.errors.due_at" />

                <div class="grid gap-3">
                    <div v-for="(item, index) in createForm.items" :key="index" class="grid gap-3 rounded-lg border border-slate-200 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-slate-950">Item {{ index + 1 }}</p>
                            <Button size="sm" variant="ghost" @click="removeItem(index)">Remover</Button>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <SelectInput :id="`request-item-category-${index}`" v-model="item.document_category_id" label="Categoria" :options="withEmpty(options.categories, 'Sem categoria')" :error="createForm.errors[`items.${index}.document_category_id`]" />
                            <TextInput :id="`request-item-title-${index}`" v-model="item.title" label="Título" required :error="createForm.errors[`items.${index}.title`]" />
                        </div>
                        <TextareaInput :id="`request-item-instructions-${index}`" v-model="item.instructions" label="Instruções" :error="createForm.errors[`items.${index}.instructions`]" />
                        <TextInput :id="`request-item-due-${index}`" v-model="item.due_at" type="date" label="Prazo do item" :error="createForm.errors[`items.${index}.due_at`]" />
                    </div>
                    <Button variant="secondary" @click="addItem">Adicionar item</Button>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" @click="createModalOpen = false">Cancelar</Button>
                <Button type="submit" form="create-document-request-form" :loading="createForm.processing">Criar</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
