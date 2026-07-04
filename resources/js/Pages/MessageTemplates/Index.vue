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
    templates: { type: Array, default: () => [] },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const createModalOpen = ref(false);
const editModalOpen = ref(false);
const selectedTemplate = ref(null);

const columns = [
    { key: 'name', label: 'Modelo' },
    { key: 'channel', label: 'Canal' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' },
];

const createForm = useForm({
    name: '',
    channel: 'email',
    purpose: 'general',
    subject: '',
    body: '',
    requires_consent: true,
    is_active: true,
});

const editForm = useForm({
    name: '',
    channel: 'email',
    purpose: 'general',
    subject: '',
    body: '',
    requires_consent: true,
    is_active: true,
});

const channelLabel = (channel) => props.options.channels.find((item) => item.value === channel)?.label ?? channel;

function submitCreate() {
    createForm.post('/message-templates', { preserveScroll: true, onSuccess: () => createModalOpen.value = false });
}

function openEdit(template) {
    selectedTemplate.value = template;
    editForm.clearErrors();
    editForm.name = template.name;
    editForm.channel = template.channel;
    editForm.purpose = template.purpose ?? 'general';
    editForm.subject = template.subject ?? '';
    editForm.body = template.body;
    editForm.requires_consent = template.requires_consent;
    editForm.is_active = template.is_active;
    editModalOpen.value = true;
}

function submitEdit() {
    editForm.patch(`/message-templates/${selectedTemplate.value.id}`, { preserveScroll: true, onSuccess: () => editModalOpen.value = false });
}

function destroyTemplate(template) {
    if (!window.confirm('Remover este modelo de mensagem?')) {
        return;
    }

    useForm({}).delete(`/message-templates/${template.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Modelos de mensagem" />
    <AppLayout title="Modelos de mensagem" active-nav="message-templates" :breadcrumbs="[{ label: 'Portal', href: '/portal' }, { label: 'Modelos de mensagem' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <DataTable :columns="columns" :rows="templates" empty-title="Nenhum modelo encontrado">
                <template #toolbar>
                    <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-950">Modelos de mensagem</h2>
                            <p class="mt-1 text-xs text-slate-500">Use variáveis como <code v-pre>{{ client_name }}</code> no corpo da mensagem.</p>
                        </div>
                        <Button v-if="can.create" size="sm" @click="createModalOpen = true">Novo modelo</Button>
                    </div>
                </template>
                <template #cell-name="{ row }">
                    <div>
                        <p class="font-semibold text-slate-950">{{ row.name }}</p>
                        <p v-if="row.subject" class="mt-1 text-xs text-slate-500">{{ row.subject }}</p>
                    </div>
                </template>
                <template #cell-channel="{ row }">{{ channelLabel(row.channel) }}</template>
                <template #cell-status="{ row }">
                    <Badge :tone="row.is_active ? 'success' : 'secondary'">{{ row.is_active ? 'Ativo' : 'Inativo' }}</Badge>
                </template>
                <template #cell-actions="{ row }">
                    <div v-if="can.update" class="flex justify-end gap-2">
                        <Button size="sm" variant="secondary" @click="openEdit(row)">Editar</Button>
                        <Button size="sm" variant="danger" @click="destroyTemplate(row)">Excluir</Button>
                    </div>
                </template>
            </DataTable>
        </div>

        <Modal v-if="createModalOpen" open title="Novo modelo" @close="createModalOpen = false">
            <form id="create-template-form" class="grid gap-4" @submit.prevent="submitCreate">
                <TextInput id="create-name" v-model="createForm.name" label="Nome" required :error="createForm.errors.name" />
                <SelectInput id="create-channel" v-model="createForm.channel" label="Canal" :options="options.channels" :error="createForm.errors.channel" />
                <TextInput id="create-purpose" v-model="createForm.purpose" label="Finalidade" :error="createForm.errors.purpose" />
                <TextInput id="create-subject" v-model="createForm.subject" label="Assunto" :error="createForm.errors.subject" />
                <TextareaInput id="create-body" v-model="createForm.body" label="Corpo" required :error="createForm.errors.body" />
                <label class="flex items-center gap-2 text-sm text-slate-700"><input v-model="createForm.requires_consent" type="checkbox" class="rounded border-slate-300" />Exige consentimento</label>
                <label class="flex items-center gap-2 text-sm text-slate-700"><input v-model="createForm.is_active" type="checkbox" class="rounded border-slate-300" />Ativo</label>
            </form>
            <template #footer>
                <Button variant="secondary" @click="createModalOpen = false">Cancelar</Button>
                <Button type="submit" form="create-template-form" :loading="createForm.processing">Salvar</Button>
            </template>
        </Modal>

        <Modal v-if="editModalOpen" open title="Editar modelo" @close="editModalOpen = false">
            <form id="edit-template-form" class="grid gap-4" @submit.prevent="submitEdit">
                <TextInput id="edit-name" v-model="editForm.name" label="Nome" required :error="editForm.errors.name" />
                <SelectInput id="edit-channel" v-model="editForm.channel" label="Canal" :options="options.channels" :error="editForm.errors.channel" />
                <TextInput id="edit-purpose" v-model="editForm.purpose" label="Finalidade" :error="editForm.errors.purpose" />
                <TextInput id="edit-subject" v-model="editForm.subject" label="Assunto" :error="editForm.errors.subject" />
                <TextareaInput id="edit-body" v-model="editForm.body" label="Corpo" required :error="editForm.errors.body" />
                <label class="flex items-center gap-2 text-sm text-slate-700"><input v-model="editForm.requires_consent" type="checkbox" class="rounded border-slate-300" />Exige consentimento</label>
                <label class="flex items-center gap-2 text-sm text-slate-700"><input v-model="editForm.is_active" type="checkbox" class="rounded border-slate-300" />Ativo</label>
            </form>
            <template #footer>
                <Button variant="secondary" @click="editModalOpen = false">Cancelar</Button>
                <Button type="submit" form="edit-template-form" :loading="editForm.processing">Salvar</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
