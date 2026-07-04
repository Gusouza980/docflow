<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import DataTable from '../../Components/Data/DataTable.vue';
import Modal from '../../Components/Overlays/Modal.vue';
import Button from '../../Components/UI/Button.vue';
import Badge from '../../Components/UI/Badge.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';
import TextareaInput from '../../Components/Forms/TextareaInput.vue';

const props = defineProps({
    announcements: { type: Array, default: () => [] },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const createModalOpen = ref(false);
const editModalOpen = ref(false);
const selectedAnnouncement = ref(null);
const withEmpty = (items, label = 'Todos os clientes') => [{ value: '', label }, ...items];

const columns = [
    { key: 'title', label: 'Comunicado' },
    { key: 'audience', label: 'Público' },
    { key: 'status', label: 'Status' },
    { key: 'published_at', label: 'Publicação' },
    { key: 'actions', label: '' },
];

const createForm = useForm({
    title: '',
    body: '',
    client_id: '',
    status: 'published',
    published_at: '',
    expires_at: '',
});

const editForm = useForm({
    title: '',
    body: '',
    client_id: '',
    status: 'published',
    published_at: '',
    expires_at: '',
});

function submitCreate() {
    createForm.post('/announcements', { preserveScroll: true, onSuccess: () => createModalOpen.value = false });
}

function openEdit(announcement) {
    selectedAnnouncement.value = announcement;
    editForm.clearErrors();
    editForm.title = announcement.title;
    editForm.body = announcement.body;
    editForm.client_id = announcement.client?.id ?? '';
    editForm.status = announcement.status;
    editForm.published_at = '';
    editForm.expires_at = '';
    editModalOpen.value = true;
}

function submitEdit() {
    editForm.patch(`/announcements/${selectedAnnouncement.value.id}`, { preserveScroll: true, onSuccess: () => editModalOpen.value = false });
}

function destroyAnnouncement(announcement) {
    if (!window.confirm('Remover este comunicado?')) {
        return;
    }

    useForm({}).delete(`/announcements/${announcement.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Comunicados" />
    <AppLayout title="Comunicados" active-nav="announcements" :breadcrumbs="[{ label: 'Portal', href: '/portal' }, { label: 'Comunicados' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <DataTable :columns="columns" :rows="announcements" empty-title="Nenhum comunicado encontrado">
                <template #toolbar>
                    <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-950">Comunicados do portal</h2>
                            <p class="mt-1 text-xs text-slate-500">Publicações visíveis na área do cliente em Mais → Comunicados.</p>
                        </div>
                        <Button v-if="can.create" size="sm" @click="createModalOpen = true">Novo comunicado</Button>
                    </div>
                </template>
                <template #cell-title="{ row }">
                    <div class="min-w-64">
                        <p class="font-semibold text-slate-950">{{ row.title }}</p>
                        <p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ row.body }}</p>
                    </div>
                </template>
                <template #cell-audience="{ row }">{{ row.client?.name ?? 'Todos os clientes' }}</template>
                <template #cell-status="{ row }">
                    <Badge :tone="row.status === 'published' ? 'success' : 'secondary'">{{ row.status === 'published' ? 'Publicado' : 'Rascunho' }}</Badge>
                </template>
                <template #cell-actions="{ row }">
                    <div v-if="can.update" class="flex justify-end gap-2">
                        <Button size="sm" variant="secondary" @click="openEdit(row)">Editar</Button>
                        <Button size="sm" variant="danger" @click="destroyAnnouncement(row)">Excluir</Button>
                    </div>
                </template>
            </DataTable>
        </div>

        <Modal v-if="createModalOpen" open title="Novo comunicado" @close="createModalOpen = false">
            <form id="create-announcement-form" class="grid gap-4" @submit.prevent="submitCreate">
                <TextInput id="create-title" v-model="createForm.title" label="Título" required :error="createForm.errors.title" />
                <TextareaInput id="create-body" v-model="createForm.body" label="Conteúdo" required :error="createForm.errors.body" />
                <SelectInput id="create-client" v-model="createForm.client_id" label="Cliente específico" :options="withEmpty(options.clients)" :error="createForm.errors.client_id" />
                <SelectInput id="create-status" v-model="createForm.status" label="Status" :options="options.statuses" :error="createForm.errors.status" />
                <TextInput id="create-published" v-model="createForm.published_at" type="datetime-local" label="Publicar em" :error="createForm.errors.published_at" />
                <TextInput id="create-expires" v-model="createForm.expires_at" type="date" label="Expira em" :error="createForm.errors.expires_at" />
            </form>
            <template #footer>
                <Button variant="secondary" @click="createModalOpen = false">Cancelar</Button>
                <Button type="submit" form="create-announcement-form" :loading="createForm.processing">Salvar</Button>
            </template>
        </Modal>

        <Modal v-if="editModalOpen" open title="Editar comunicado" @close="editModalOpen = false">
            <form id="edit-announcement-form" class="grid gap-4" @submit.prevent="submitEdit">
                <TextInput id="edit-title" v-model="editForm.title" label="Título" required :error="editForm.errors.title" />
                <TextareaInput id="edit-body" v-model="editForm.body" label="Conteúdo" required :error="editForm.errors.body" />
                <SelectInput id="edit-client" v-model="editForm.client_id" label="Cliente específico" :options="withEmpty(options.clients)" :error="editForm.errors.client_id" />
                <SelectInput id="edit-status" v-model="editForm.status" label="Status" :options="options.statuses" :error="editForm.errors.status" />
                <TextInput id="edit-published" v-model="editForm.published_at" type="datetime-local" label="Publicar em" :error="editForm.errors.published_at" />
                <TextInput id="edit-expires" v-model="editForm.expires_at" type="date" label="Expira em" :error="editForm.errors.expires_at" />
            </form>
            <template #footer>
                <Button variant="secondary" @click="editModalOpen = false">Cancelar</Button>
                <Button type="submit" form="edit-announcement-form" :loading="editForm.processing">Salvar</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
