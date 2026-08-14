<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import PlatformLayout from '../../../Layouts/PlatformLayout.vue';
import Alert from '../../../Components/Feedback/Alert.vue';
import DataTable from '../../../Components/Data/DataTable.vue';
import Button from '../../../Components/UI/Button.vue';
import Modal from '../../../Components/Overlays/Modal.vue';
import SelectInput from '../../../Components/Forms/SelectInput.vue';
import TextInput from '../../../Components/Forms/TextInput.vue';
import StatusPill from '../../../Components/UI/StatusPill.vue';

const props = defineProps({
    organizations: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statusOptions: { type: Array, default: () => [] },
    planOptions: { type: Array, default: () => [] },
});

const page = usePage();
const createModalOpen = ref(false);

const filterForm = useForm({
    status: props.filters.status ?? '',
    search: props.filters.search ?? '',
});

const createForm = useForm({
    owner_name: '',
    owner_email: '',
    name: '',
    document: '',
    email: '',
    phone: '',
    timezone: 'America/Sao_Paulo',
    plan_id: '',
});

const planSelectOptions = [
    { value: '', label: 'Essencial (padrão)' },
    ...props.planOptions,
];

function openCreateModal() {
    createModalOpen.value = true;
}

function closeCreateModal() {
    createModalOpen.value = false;
}

function provisionTenant() {
    createForm.post('/platform/organizations', {
        preserveScroll: true,
        onSuccess: () => {
            createModalOpen.value = false;
            createForm.reset();
        },
    });
}

const columns = [
    { key: 'name', label: 'Organização' },
    { key: 'status', label: 'Status' },
    { key: 'members_count', label: 'Membros' },
    { key: 'clients_count', label: 'Clientes' },
    { key: 'created_at', label: 'Criada em' },
    { key: 'actions', label: '' },
];

function applyFilters() {
    router.get('/platform/organizations', filterForm.data(), { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head title="Platform · Organizações" />
    <PlatformLayout title="Organizações (tenants)" active-nav="organizations" :breadcrumbs="[{ label: 'Organizações' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.reset_url" tone="info">
                Link para definir senha (copie se o e-mail não chegar): {{ page.props.flash.reset_url }}
            </Alert>

            <form class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[180px_1fr_auto_auto]" @submit.prevent="applyFilters">
                <SelectInput id="filter-status" v-model="filterForm.status" label="Status" :options="statusOptions" />
                <TextInput id="filter-search" v-model="filterForm.search" label="Buscar" placeholder="Nome, documento ou e-mail" />
                <div class="flex items-end gap-2">
                    <Button type="submit">Filtrar</Button>
                    <Button type="button" @click="openCreateModal">Novo cliente</Button>
                </div>
            </form>

            <DataTable :columns="columns" :rows="organizations.data" empty-title="Nenhuma organização encontrada">
                <template #cell-name="{ row }">
                    <div>
                        <p class="font-semibold text-slate-950">{{ row.name }}</p>
                        <p v-if="row.document" class="mt-0.5 text-xs text-slate-500">{{ row.document }}</p>
                    </div>
                </template>
                <template #cell-status="{ row }"><StatusPill :status="row.status" /></template>
                <template #cell-actions="{ row }">
                    <Link :href="`/platform/organizations/${row.id}`" class="text-sm font-semibold text-violet-700 hover:text-violet-900">Detalhes</Link>
                </template>
            </DataTable>

            <div v-if="organizations.meta.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
                <p>Página {{ organizations.meta.current_page }} de {{ organizations.meta.last_page }} · {{ organizations.meta.total }} registros</p>
                <div class="flex gap-2">
                    <Link
                        v-if="organizations.meta.current_page > 1"
                        :href="`/platform/organizations?page=${organizations.meta.current_page - 1}`"
                        preserve-state
                        class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-50"
                    >Anterior</Link>
                    <Link
                        v-if="organizations.meta.current_page < organizations.meta.last_page"
                        :href="`/platform/organizations?page=${organizations.meta.current_page + 1}`"
                        preserve-state
                        class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-50"
                    >Próxima</Link>
                </div>
            </div>
        </div>

        <Modal
            v-if="createModalOpen"
            open
            title="Novo cliente"
            description="Cria o usuário administrador e a primeira organização. O cliente recebe um e-mail para definir a senha."
            @close="closeCreateModal"
        >
            <form id="provision-tenant-form" class="grid gap-4" @submit.prevent="provisionTenant">
                <TextInput id="owner-name" v-model="createForm.owner_name" label="Nome do responsável" required :error="createForm.errors.owner_name" />
                <TextInput id="owner-email" v-model="createForm.owner_email" type="email" label="E-mail do responsável" required :error="createForm.errors.owner_email" />
                <TextInput id="org-name" v-model="createForm.name" label="Nome da organização" required :error="createForm.errors.name" />
                <TextInput id="org-document" v-model="createForm.document" label="Documento" :error="createForm.errors.document" />
                <TextInput id="org-email" v-model="createForm.email" type="email" label="E-mail da organização" :error="createForm.errors.email" />
                <TextInput id="org-phone" v-model="createForm.phone" label="Telefone" :error="createForm.errors.phone" />
                <TextInput id="org-timezone" v-model="createForm.timezone" label="Fuso horário" required :error="createForm.errors.timezone" />
                <SelectInput id="org-plan" v-model="createForm.plan_id" label="Plano inicial" :options="planSelectOptions" :error="createForm.errors.plan_id" />
            </form>
            <template #actions>
                <Button type="submit" form="provision-tenant-form" :loading="createForm.processing" :disabled="createForm.processing">Provisionar cliente</Button>
            </template>
        </Modal>
    </PlatformLayout>
</template>
