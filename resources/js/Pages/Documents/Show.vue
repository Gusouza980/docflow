<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Card from '../../Components/UI/Card.vue';
import Button from '../../Components/UI/Button.vue';
import Badge from '../../Components/UI/Badge.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';
import Modal from '../../Components/Overlays/Modal.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';
import TextareaInput from '../../Components/Forms/TextareaInput.vue';

const props = defineProps({
    document: { type: Object, required: true },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const editModalOpen = ref(false);
const versionModalOpen = ref(false);
const withEmpty = (items, label) => [{ value: '', label }, ...items];

const statusOptions = [
    { value: 'received', label: 'Recebido' },
    { value: 'approved', label: 'Aprovado' },
    { value: 'rejected', label: 'Recusado' },
    { value: 'expired', label: 'Expirado' },
    { value: 'replaced', label: 'Substituído' },
];
const visibilityOptions = [
    { value: 'internal', label: 'Interno' },
    { value: 'client', label: 'Cliente' },
    { value: 'restricted', label: 'Restrito' },
    { value: 'confidential', label: 'Confidencial' },
];
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

const editForm = useForm({
    client_id: props.document.client?.id ?? '',
    document_category_id: props.document.category?.id ?? '',
    title: props.document.title,
    description: props.document.description ?? '',
    status: props.document.status,
    visibility: props.document.visibility,
    sensitivity: props.document.sensitivity,
    expires_at: props.document.expires_at ?? '',
    rejection_reason: props.document.rejection_reason ?? '',
});

const versionForm = useForm({
    source: 'internal',
    file: null,
});

function submitEdit() {
    editForm.patch(`/documents/${props.document.id}`, {
        preserveScroll: true,
        onSuccess: () => editModalOpen.value = false,
    });
}

function submitVersion() {
    versionForm.post(`/documents/${props.document.id}/versions`, {
        preserveScroll: true,
        onSuccess: () => versionModalOpen.value = false,
    });
}
</script>

<template>
    <Head :title="document.title" />
    <AppLayout :title="document.title" active-nav="documents" :breadcrumbs="[{ label: 'Documentos', href: '/documents' }, { label: document.title }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <StatusPill :status="document.status" />
                        <Badge tone="secondary">{{ document.visibility }}</Badge>
                        <Badge v-if="document.sensitivity !== 'normal'" tone="warning">{{ document.sensitivity }}</Badge>
                    </div>
                    <p class="mt-2 text-sm text-slate-500">{{ document.description || 'Sem descrição' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a :href="document.view_href" target="_blank" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-800 hover:bg-slate-50">Visualizar</a>
                    <a :href="document.download_href" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-800 hover:bg-slate-50">Baixar</a>
                    <Button v-if="can.update" variant="secondary" @click="versionModalOpen = true">Nova versão</Button>
                    <Button v-if="can.update" @click="editModalOpen = true">Editar</Button>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                <Card title="Metadados">
                    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt class="text-xs font-semibold uppercase text-slate-500">Cliente</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ document.client?.name ?? 'Sem cliente' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase text-slate-500">Categoria</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ document.category?.name ?? 'Sem categoria' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase text-slate-500">Vencimento</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ document.expires_at || 'Sem vencimento' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase text-slate-500">Arquivo atual</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ document.latest_version?.original_name ?? 'Sem arquivo' }}</dd>
                        </div>
                        <div v-if="document.rejection_reason" class="sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase text-slate-500">Motivo de recusa</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ document.rejection_reason }}</dd>
                        </div>
                    </dl>
                </Card>

                <Card title="Versões">
                    <div class="grid gap-3">
                        <div v-for="version in document.versions" :key="version.id" class="rounded-lg border border-slate-200 p-3">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-semibold text-slate-950">v{{ version.version_number }}</p>
                                <Badge :tone="version.replaced_at ? 'neutral' : 'success'">{{ version.replaced_at ? 'Substituída' : 'Atual' }}</Badge>
                            </div>
                            <p class="mt-1 text-sm text-slate-700">{{ version.original_name }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ version.source }} · {{ version.uploaded_by || 'Usuário' }}</p>
                        </div>
                    </div>
                </Card>
            </div>
        </div>

        <Modal v-if="editModalOpen" open title="Editar documento" @close="editModalOpen = false">
            <form id="edit-document-form" class="grid gap-4" @submit.prevent="submitEdit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <SelectInput id="edit-document-client" v-model="editForm.client_id" label="Cliente" :options="withEmpty(options.clients, 'Sem cliente')" :error="editForm.errors.client_id" />
                    <SelectInput id="edit-document-category" v-model="editForm.document_category_id" label="Categoria" :options="withEmpty(options.categories, 'Sem categoria')" :error="editForm.errors.document_category_id" />
                </div>
                <TextInput id="edit-document-title" v-model="editForm.title" label="Título" required :error="editForm.errors.title" />
                <TextareaInput id="edit-document-description" v-model="editForm.description" label="Descrição" :error="editForm.errors.description" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <SelectInput id="edit-document-status" v-model="editForm.status" label="Status" :options="statusOptions" :error="editForm.errors.status" />
                    <TextInput id="edit-document-expires" v-model="editForm.expires_at" type="date" label="Vencimento" :error="editForm.errors.expires_at" />
                    <SelectInput id="edit-document-visibility" v-model="editForm.visibility" label="Visibilidade" :options="visibilityOptions" :error="editForm.errors.visibility" />
                    <SelectInput id="edit-document-sensitivity" v-model="editForm.sensitivity" label="Sensibilidade" :options="sensitivityOptions" :error="editForm.errors.sensitivity" />
                </div>
                <TextareaInput id="edit-document-rejection" v-model="editForm.rejection_reason" label="Motivo de recusa" :error="editForm.errors.rejection_reason" />
            </form>
            <template #footer>
                <Button variant="secondary" @click="editModalOpen = false">Cancelar</Button>
                <Button type="submit" form="edit-document-form" :loading="editForm.processing">Salvar</Button>
            </template>
        </Modal>

        <Modal v-if="versionModalOpen" open title="Nova versão" description="O arquivo atual será preservado no histórico." @close="versionModalOpen = false">
            <form id="document-version-form" class="grid gap-4" @submit.prevent="submitVersion">
                <SelectInput id="document-version-source" v-model="versionForm.source" label="Origem" :options="sourceOptions" :error="versionForm.errors.source" />
                <label class="grid gap-1 text-sm font-medium text-slate-700">
                    Arquivo
                    <input class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" type="file" @input="versionForm.file = $event.target.files[0]" />
                    <span v-if="versionForm.errors.file" class="text-xs font-medium text-red-600">{{ versionForm.errors.file }}</span>
                </label>
            </form>
            <template #footer>
                <Button variant="secondary" @click="versionModalOpen = false">Cancelar</Button>
                <Button type="submit" form="document-version-form" :loading="versionForm.processing">Enviar versão</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
