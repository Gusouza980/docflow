<script setup>
import { ref } from 'vue';
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
    documents: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const uploadModalOpen = ref(false);
const categoryModalOpen = ref(false);
const editingCategory = ref(null);

const withEmpty = (items, label = 'Todos') => [{ value: '', label }, ...items];

const statusOptions = [
    { value: '', label: 'Todos' },
    { value: 'received', label: 'Recebido' },
    { value: 'approved', label: 'Aprovado' },
    { value: 'rejected', label: 'Recusado' },
    { value: 'expired', label: 'Expirado' },
    { value: 'replaced', label: 'Substituído' },
];
const visibilityOptions = [
    { value: '', label: 'Todas' },
    { value: 'internal', label: 'Interno' },
    { value: 'client', label: 'Cliente' },
    { value: 'restricted', label: 'Restrito' },
    { value: 'confidential', label: 'Confidencial' },
];
const strictVisibilityOptions = visibilityOptions.filter((option) => option.value);
const sensitivityOptions = [
    { value: 'normal', label: 'Normal' },
    { value: 'sensitive', label: 'Sensível' },
    { value: 'confidential', label: 'Confidencial' },
];
const sourceOptions = [
    { value: 'internal', label: 'Interno' },
    { value: 'portal', label: 'Portal' },
    { value: 'email', label: 'E-mail' },
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'import', label: 'Importação' },
];
const dateFilterOptions = [
    { value: '', label: 'Todos' },
    { value: 'expired', label: 'Vencidos' },
    { value: 'expiring_soon', label: 'Vencem em 30 dias' },
];
const columns = [
    { key: 'title', label: 'Documento' },
    { key: 'status', label: 'Status' },
    { key: 'client', label: 'Cliente' },
    { key: 'expires_at', label: 'Vencimento' },
    { key: 'actions', label: '' },
];
const categoryColumns = [
    { key: 'name', label: 'Categoria' },
    { key: 'sensitivity', label: 'Sensibilidade' },
    { key: 'validity_days', label: 'Validade' },
    { key: 'actions', label: '' },
];

const filterForm = useForm({
    search: props.filters.search ?? '',
    client_id: props.filters.client_id ?? '',
    document_category_id: props.filters.document_category_id ?? '',
    status: props.filters.status ?? '',
    visibility: props.filters.visibility ?? '',
    date_filter: props.filters.date_filter ?? '',
});

const uploadForm = useForm({
    client_id: '',
    document_category_id: '',
    title: '',
    description: '',
    status: 'received',
    visibility: 'internal',
    sensitivity: 'normal',
    expires_at: '',
    source: 'internal',
    file: null,
});

const categoryForm = useForm({
    name: '',
    description: '',
    validity_days: '',
    sensitivity: 'normal',
    is_active: true,
});

function applyFilters() {
    router.get('/documents', filterForm.data(), { preserveState: true, preserveScroll: true });
}

function clearFilters() {
    filterForm.search = '';
    filterForm.client_id = '';
    filterForm.document_category_id = '';
    filterForm.status = '';
    filterForm.visibility = '';
    filterForm.date_filter = '';
    applyFilters();
}

function openUploadModal() {
    uploadForm.clearErrors();
    uploadModalOpen.value = true;
}

function submitUpload() {
    uploadForm.post('/documents', {
        preserveScroll: true,
        onSuccess: () => uploadModalOpen.value = false,
    });
}

function openCategoryModal(category = null) {
    editingCategory.value = category;
    categoryForm.clearErrors();
    categoryForm.name = category?.name ?? '';
    categoryForm.description = category?.description ?? '';
    categoryForm.validity_days = category?.validity_days ?? '';
    categoryForm.sensitivity = category?.sensitivity ?? 'normal';
    categoryForm.is_active = category?.is_active ?? true;
    categoryModalOpen.value = true;
}

function submitCategory() {
    const options = {
        preserveScroll: true,
        onSuccess: () => categoryModalOpen.value = false,
    };

    if (editingCategory.value) {
        categoryForm.patch(`/document-categories/${editingCategory.value.id}`, options);
        return;
    }

    categoryForm.post('/document-categories', options);
}
</script>

<template>
    <Head title="Documentos" />
    <AppLayout title="Documentos" active-nav="documents" :breadcrumbs="[{ label: 'Documentos' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <DataTable :columns="columns" :rows="documents.data" empty-title="Nenhum documento encontrado">
                <template #toolbar>
                    <div class="grid gap-3 border-b border-slate-200 px-4 py-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-sm font-semibold text-slate-950">Acervo de documentos</h2>
                                <p class="mt-1 text-xs text-slate-500">Uploads internos, versionamento, vencimentos e classificação de acesso.</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button v-if="can.manage_categories" size="sm" variant="secondary" @click="openCategoryModal()">Nova categoria</Button>
                                <Button v-if="can.create" size="sm" @click="openUploadModal">Enviar documento</Button>
                            </div>
                        </div>
                        <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,0.9fr)_minmax(0,0.9fr)]" @submit.prevent="applyFilters">
                            <TextInput id="document-search" v-model="filterForm.search" label="Busca" placeholder="Título do documento" />
                            <SelectInput id="document-client" v-model="filterForm.client_id" label="Cliente" :options="withEmpty(options.clients)" />
                            <SelectInput id="document-category" v-model="filterForm.document_category_id" label="Categoria" :options="withEmpty(options.categories)" />
                            <SelectInput id="document-status" v-model="filterForm.status" label="Status" :options="statusOptions" />
                            <SelectInput id="document-date-filter" v-model="filterForm.date_filter" label="Vencimento" :options="dateFilterOptions" />
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
                        <div class="mt-1 flex flex-wrap gap-2 text-xs text-slate-500">
                            <span>{{ row.category?.name ?? 'Sem categoria' }}</span>
                            <span>{{ row.latest_version?.original_name ?? 'Sem arquivo' }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <Badge tone="secondary">{{ row.visibility }}</Badge>
                            <Badge v-if="row.sensitivity !== 'normal'" tone="warning">{{ row.sensitivity }}</Badge>
                        </div>
                    </div>
                </template>
                <template #cell-status="{ row }"><StatusPill :status="row.status" /></template>
                <template #cell-client="{ row }">{{ row.client?.name ?? 'Sem cliente' }}</template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end gap-2">
                        <a :href="row.download_href" class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-[13px] font-semibold text-slate-800 hover:bg-slate-50">Baixar</a>
                        <Link :href="row.href" class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-[13px] font-semibold text-slate-800 hover:bg-slate-50">Abrir</Link>
                    </div>
                </template>
            </DataTable>

            <Pagination :current-page="documents.meta.current_page" :total-pages="documents.meta.last_page" :per-page="documents.meta.per_page" />

            <DataTable :columns="categoryColumns" :rows="categories" empty-title="Nenhuma categoria cadastrada">
                <template #toolbar>
                    <div class="border-b border-slate-200 px-4 py-3">
                        <h2 class="text-sm font-semibold text-slate-950">Categorias de documentos</h2>
                    </div>
                </template>
                <template #cell-name="{ row }">
                    <div>
                        <p class="font-semibold text-slate-950">{{ row.name }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ row.description || 'Sem descrição' }}</p>
                    </div>
                </template>
                <template #cell-sensitivity="{ row }"><Badge :tone="row.sensitivity === 'normal' ? 'neutral' : 'warning'">{{ row.sensitivity }}</Badge></template>
                <template #cell-validity_days="{ row }">{{ row.validity_days ? `${row.validity_days} dias` : 'Sem validade padrão' }}</template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end gap-2">
                        <Button v-if="can.manage_categories" size="sm" variant="secondary" @click="openCategoryModal(row)">Editar</Button>
                    </div>
                </template>
            </DataTable>
        </div>

        <Modal v-if="uploadModalOpen" open title="Enviar documento" description="Registre o documento e armazene o arquivo em área privada." @close="uploadModalOpen = false">
            <form id="upload-document-form" class="grid gap-4" @submit.prevent="submitUpload">
                <div class="grid gap-4 sm:grid-cols-2">
                    <SelectInput id="upload-client" v-model="uploadForm.client_id" label="Cliente" :options="withEmpty(options.clients, 'Sem cliente')" :error="uploadForm.errors.client_id" />
                    <SelectInput id="upload-category" v-model="uploadForm.document_category_id" label="Categoria" :options="withEmpty(options.categories, 'Sem categoria')" :error="uploadForm.errors.document_category_id" />
                </div>
                <TextInput id="upload-title" v-model="uploadForm.title" label="Título" required :error="uploadForm.errors.title" />
                <TextareaInput id="upload-description" v-model="uploadForm.description" label="Descrição" :error="uploadForm.errors.description" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <SelectInput id="upload-visibility" v-model="uploadForm.visibility" label="Visibilidade" :options="strictVisibilityOptions" :error="uploadForm.errors.visibility" />
                    <SelectInput id="upload-sensitivity" v-model="uploadForm.sensitivity" label="Sensibilidade" :options="sensitivityOptions" :error="uploadForm.errors.sensitivity" />
                    <TextInput id="upload-expires" v-model="uploadForm.expires_at" type="date" label="Vencimento" :error="uploadForm.errors.expires_at" />
                    <SelectInput id="upload-source" v-model="uploadForm.source" label="Origem" :options="sourceOptions" :error="uploadForm.errors.source" />
                </div>
                <label class="grid gap-1 text-sm font-medium text-slate-700">
                    Arquivo
                    <input class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" type="file" @input="uploadForm.file = $event.target.files[0]" />
                    <span v-if="uploadForm.errors.file" class="text-xs font-medium text-red-600">{{ uploadForm.errors.file }}</span>
                </label>
            </form>
            <template #footer>
                <Button variant="secondary" @click="uploadModalOpen = false">Cancelar</Button>
                <Button type="submit" form="upload-document-form" :loading="uploadForm.processing">Enviar</Button>
            </template>
        </Modal>

        <Modal v-if="categoryModalOpen" open :title="editingCategory ? 'Editar categoria' : 'Nova categoria'" @close="categoryModalOpen = false">
            <form id="category-form" class="grid gap-4" @submit.prevent="submitCategory">
                <TextInput id="category-name" v-model="categoryForm.name" label="Nome" required :error="categoryForm.errors.name" />
                <TextareaInput id="category-description" v-model="categoryForm.description" label="Descrição" :error="categoryForm.errors.description" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <TextInput id="category-validity" v-model="categoryForm.validity_days" type="number" label="Validade padrão (dias)" :error="categoryForm.errors.validity_days" />
                    <SelectInput id="category-sensitivity" v-model="categoryForm.sensitivity" label="Sensibilidade" :options="sensitivityOptions" :error="categoryForm.errors.sensitivity" />
                </div>
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input v-model="categoryForm.is_active" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-300" />
                    Categoria ativa
                </label>
            </form>
            <template #footer>
                <Button variant="secondary" @click="categoryModalOpen = false">Cancelar</Button>
                <Button type="submit" form="category-form" :loading="categoryForm.processing">Salvar</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
