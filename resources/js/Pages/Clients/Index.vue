<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
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
    clients: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const createModalOpen = ref(false);

const filterForm = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    type: props.filters.type ?? '',
    responsible_member_id: props.filters.responsible_member_id ?? '',
});

const createForm = useForm({
    type: 'individual',
    display_name: '',
    document_number: '',
    status: 'active',
    priority: 'normal',
    risk_level: 'low',
    potential_revenue_cents: '',
    origin: '',
    access_policy: 'all_members',
    internal_notes: '',
    entered_at: '',
    responsible_member_ids: [],
    individual_profile: {
        full_name: '',
        rg: '',
        birth_date: '',
        marital_status: '',
        profession: '',
    },
    company_profile: {
        legal_name: '',
        trade_name: '',
        state_registration: '',
        municipal_registration: '',
        tax_regime: '',
        main_cnae: '',
    },
});

const columns = [
    { key: 'display_name', label: 'Cliente' },
    { key: 'status', label: 'Status' },
    { key: 'priority', label: 'Prioridade' },
    { key: 'primary_responsible', label: 'Responsável' },
    { key: 'actions', label: '' },
];

const statusOptions = [
    { value: '', label: 'Todos' },
    { value: 'active', label: 'Ativo' },
    { value: 'inactive', label: 'Inativo' },
    { value: 'negotiation', label: 'Negociação' },
    { value: 'delinquent', label: 'Inadimplente' },
    { value: 'closed', label: 'Encerrado' },
];

const typeOptions = [
    { value: '', label: 'Todos' },
    { value: 'individual', label: 'Pessoa física' },
    { value: 'company', label: 'Pessoa jurídica' },
];

const clientTypeOptions = typeOptions.filter((option) => option.value);
const priorityOptions = [
    { value: 'low', label: 'Baixa' },
    { value: 'normal', label: 'Normal' },
    { value: 'high', label: 'Alta' },
];
const riskOptions = [
    { value: 'low', label: 'Baixo' },
    { value: 'medium', label: 'Médio' },
    { value: 'high', label: 'Alto' },
];
const accessOptions = [
    { value: 'all_members', label: 'Todos os membros' },
    { value: 'restricted', label: 'Restrito' },
];

const responsibleFilterOptions = [{ value: '', label: 'Todos' }, ...props.options.members];

watch(() => createForm.type, (type) => {
    if (type === 'individual' && !createForm.individual_profile.full_name) {
        createForm.individual_profile.full_name = createForm.display_name;
    }

    if (type === 'company' && !createForm.company_profile.legal_name) {
        createForm.company_profile.legal_name = createForm.display_name;
    }
});

function applyFilters() {
    router.get('/clients', filterForm.data(), {
        preserveState: true,
        preserveScroll: true,
    });
}

function clearFilters() {
    filterForm.search = '';
    filterForm.status = '';
    filterForm.type = '';
    filterForm.responsible_member_id = '';
    applyFilters();
}

function toggleResponsible(id) {
    const index = createForm.responsible_member_ids.indexOf(id);

    if (index === -1) {
        createForm.responsible_member_ids.push(id);
        return;
    }

    createForm.responsible_member_ids.splice(index, 1);
}

function openCreateModal() {
    createForm.clearErrors();
    createModalOpen.value = true;
}

function closeCreateModal() {
    if (createForm.processing) {
        return;
    }

    createForm.clearErrors();
    createModalOpen.value = false;
}

function submitCreate() {
    createForm.post('/clients', {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Clientes" />
    <AppLayout title="Clientes" active-nav="clients" :breadcrumbs="[{ label: 'Clientes' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <DataTable :columns="columns" :rows="clients.data" empty-title="Nenhum cliente encontrado">
                <template #toolbar>
                    <div class="grid gap-3 border-b border-slate-200 px-4 py-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-sm font-semibold text-slate-950">Base de clientes</h2>
                                <p class="mt-1 text-xs text-slate-500">Cadastros PF/PJ, responsáveis, classificação e permissões de acesso.</p>
                            </div>
                            <Button v-if="can.create" size="sm" @click="openCreateModal">Novo cliente</Button>
                        </div>
                        <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.25fr)]" @submit.prevent="applyFilters">
                            <TextInput id="client-search" v-model="filterForm.search" label="Busca" placeholder="Nome ou documento" />
                            <SelectInput id="client-status" v-model="filterForm.status" label="Status" :options="statusOptions" />
                            <SelectInput id="client-type" v-model="filterForm.type" label="Tipo" :options="typeOptions" />
                            <SelectInput id="client-responsible" v-model="filterForm.responsible_member_id" label="Responsável" :options="responsibleFilterOptions" />
                            <div class="flex items-end gap-2 md:col-span-2 xl:col-span-4">
                                <Button type="submit" variant="secondary">Filtrar</Button>
                                <Button variant="ghost" @click="clearFilters">Limpar</Button>
                            </div>
                        </form>
                    </div>
                </template>

                <template #cell-display_name="{ row }">
                    <div class="min-w-64">
                        <Link :href="row.href" class="font-semibold text-slate-950 hover:text-blue-700">{{ row.display_name }}</Link>
                        <div class="mt-1 flex flex-wrap gap-2 text-xs text-slate-500">
                            <span>{{ row.type === 'company' ? 'Pessoa jurídica' : 'Pessoa física' }}</span>
                            <span v-if="row.document_number">· {{ row.document_number }}</span>
                        </div>
                        <div v-if="row.tags.length" class="mt-2 flex flex-wrap gap-1">
                            <Badge v-for="tag in row.tags" :key="tag.id" tone="secondary">{{ tag.name }}</Badge>
                        </div>
                    </div>
                </template>
                <template #cell-status="{ row }">
                    <StatusPill :status="row.status" />
                </template>
                <template #cell-priority="{ row }">
                    <span class="text-sm font-medium text-slate-700">{{ row.priority }}</span>
                </template>
                <template #cell-primary_responsible="{ row }">
                    <span class="text-sm text-slate-600">{{ row.primary_responsible?.name ?? 'Sem responsável' }}</span>
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end">
                        <Link :href="row.href" class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-[13px] font-semibold text-slate-800 hover:bg-slate-50">Abrir</Link>
                    </div>
                </template>
            </DataTable>

            <Pagination :current-page="clients.meta.current_page" :total-pages="clients.meta.last_page" :per-page="clients.meta.per_page" />
        </div>

        <Modal v-if="createModalOpen" open title="Novo cliente" description="Cadastre cliente PF ou PJ e defina responsáveis internos." @close="closeCreateModal">
            <form id="create-client-form" class="grid gap-4" @submit.prevent="submitCreate">
                <div class="grid gap-4 sm:grid-cols-2">
                    <SelectInput id="client-create-type" v-model="createForm.type" label="Tipo" :options="clientTypeOptions" :error="createForm.errors.type" />
                    <TextInput id="client-create-document" v-model="createForm.document_number" label="Documento" :error="createForm.errors.document_number" />
                </div>
                <TextInput id="client-create-name" v-model="createForm.display_name" label="Nome de exibição" required :error="createForm.errors.display_name" />

                <div v-if="createForm.type === 'individual'" class="grid gap-4 sm:grid-cols-2">
                    <TextInput id="client-create-full-name" v-model="createForm.individual_profile.full_name" label="Nome completo" required :error="createForm.errors['individual_profile.full_name']" />
                    <TextInput id="client-create-rg" v-model="createForm.individual_profile.rg" label="RG" :error="createForm.errors['individual_profile.rg']" />
                    <TextInput id="client-create-birth-date" v-model="createForm.individual_profile.birth_date" type="date" label="Nascimento" :error="createForm.errors['individual_profile.birth_date']" />
                    <TextInput id="client-create-profession" v-model="createForm.individual_profile.profession" label="Profissão" :error="createForm.errors['individual_profile.profession']" />
                </div>

                <div v-else class="grid gap-4 sm:grid-cols-2">
                    <TextInput id="client-create-legal-name" v-model="createForm.company_profile.legal_name" label="Razão social" required :error="createForm.errors['company_profile.legal_name']" />
                    <TextInput id="client-create-trade-name" v-model="createForm.company_profile.trade_name" label="Nome fantasia" :error="createForm.errors['company_profile.trade_name']" />
                    <TextInput id="client-create-tax-regime" v-model="createForm.company_profile.tax_regime" label="Regime tributário" :error="createForm.errors['company_profile.tax_regime']" />
                    <TextInput id="client-create-cnae" v-model="createForm.company_profile.main_cnae" label="CNAE principal" :error="createForm.errors['company_profile.main_cnae']" />
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <SelectInput id="client-create-status" v-model="createForm.status" label="Status" :options="statusOptions.filter((option) => option.value)" :error="createForm.errors.status" />
                    <SelectInput id="client-create-priority" v-model="createForm.priority" label="Prioridade" :options="priorityOptions" :error="createForm.errors.priority" />
                    <SelectInput id="client-create-risk" v-model="createForm.risk_level" label="Risco" :options="riskOptions" :error="createForm.errors.risk_level" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <TextInput id="client-create-origin" v-model="createForm.origin" label="Origem" :error="createForm.errors.origin" />
                    <TextInput id="client-create-entered" v-model="createForm.entered_at" type="date" label="Entrada" :error="createForm.errors.entered_at" />
                </div>
                <SelectInput id="client-create-access" v-model="createForm.access_policy" label="Acesso" :options="accessOptions" :error="createForm.errors.access_policy" />
                <div>
                    <p class="mb-2 text-sm font-semibold text-slate-700">Responsáveis</p>
                    <div class="grid gap-2">
                        <label v-for="member in options.members" :key="member.value" class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" :checked="createForm.responsible_member_ids.includes(member.value)" @change="toggleResponsible(member.value)" />
                            <span>{{ member.label }}</span>
                        </label>
                    </div>
                    <p v-if="createForm.errors.responsible_member_ids" class="mt-2 text-sm font-medium text-red-600">{{ createForm.errors.responsible_member_ids }}</p>
                </div>
                <TextareaInput id="client-create-notes" v-model="createForm.internal_notes" label="Notas internas" :error="createForm.errors.internal_notes" />
            </form>
            <template #actions>
                <Button type="submit" form="create-client-form" :loading="createForm.processing" :disabled="createForm.processing">Cadastrar cliente</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
