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
    documentRequest: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const uploadModalOpen = ref(false);
const rejectModalOpen = ref(false);
const cancelModalOpen = ref(false);
const selectedItem = ref(null);

const sourceOptions = [
    { value: 'internal', label: 'Interno' },
    { value: 'portal', label: 'Portal' },
    { value: 'email', label: 'E-mail' },
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'import', label: 'Importação' },
];

const uploadForm = useForm({
    title: '',
    source: 'portal',
    file: null,
});
const rejectForm = useForm({
    rejection_reason: '',
});
const cancelForm = useForm({
    cancellation_reason: '',
});
const approveForm = useForm({});

function openUpload(item) {
    selectedItem.value = item;
    uploadForm.reset();
    uploadForm.clearErrors();
    uploadForm.title = item.title;
    uploadModalOpen.value = true;
}

function submitUpload() {
    uploadForm.post(`/document-request-items/${selectedItem.value.id}/upload`, {
        preserveScroll: true,
        onSuccess: () => uploadModalOpen.value = false,
    });
}

function openReject(item) {
    selectedItem.value = item;
    rejectForm.reset();
    rejectForm.clearErrors();
    rejectModalOpen.value = true;
}

function submitReject() {
    rejectForm.patch(`/document-request-items/${selectedItem.value.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => rejectModalOpen.value = false,
    });
}

function approve(item) {
    approveForm.patch(`/document-request-items/${item.id}/approve`, { preserveScroll: true });
}

function submitCancel() {
    cancelForm.patch(`/document-requests/${props.documentRequest.id}/cancel`, {
        preserveScroll: true,
        onSuccess: () => cancelModalOpen.value = false,
    });
}
</script>

<template>
    <Head :title="documentRequest.title" />
    <AppLayout :title="documentRequest.title" active-nav="document-requests" :breadcrumbs="[{ label: 'Solicitações', href: '/document-requests' }, { label: documentRequest.title }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <StatusPill :status="documentRequest.status" />
                        <Badge tone="secondary">{{ documentRequest.client.name }}</Badge>
                    </div>
                    <p class="mt-2 text-sm text-slate-500">{{ documentRequest.instructions || 'Sem instruções gerais' }}</p>
                </div>
                <Button v-if="can.update && documentRequest.status === 'pending'" variant="danger" @click="cancelModalOpen = true">Cancelar solicitação</Button>
            </div>

            <Card title="Itens solicitados">
                <div class="grid gap-3">
                    <div v-for="item in documentRequest.items" :key="item.id" class="rounded-lg border border-slate-200 p-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-semibold text-slate-950">{{ item.title }}</h3>
                                    <StatusPill :status="item.status" />
                                    <Badge v-if="item.category" tone="secondary">{{ item.category.name }}</Badge>
                                </div>
                                <p class="mt-1 text-sm text-slate-500">{{ item.instructions || 'Sem instruções específicas' }}</p>
                                <p class="mt-2 text-sm text-slate-700">Prazo: {{ item.due_at || documentRequest.due_at || 'Sem prazo' }}</p>
                                <p v-if="item.rejection_reason" class="mt-2 text-sm text-red-700">{{ item.rejection_reason }}</p>
                                <Link v-if="item.document" :href="item.document.href" class="mt-2 inline-flex text-sm font-semibold text-blue-700 hover:text-blue-800">{{ item.document.title }}</Link>
                            </div>
                            <div v-if="can.update && documentRequest.status === 'pending'" class="flex flex-wrap gap-2">
                                <Button size="sm" variant="secondary" @click="openUpload(item)">Enviar arquivo</Button>
                                <Button v-if="item.document" size="sm" variant="secondary" @click="approve(item)">Aprovar</Button>
                                <Button v-if="item.document" size="sm" variant="danger" @click="openReject(item)">Recusar</Button>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>
        </div>

        <Modal v-if="uploadModalOpen" open title="Enviar arquivo" @close="uploadModalOpen = false">
            <form id="request-item-upload-form" class="grid gap-4" @submit.prevent="submitUpload">
                <TextInput id="request-item-upload-title" v-model="uploadForm.title" label="Título do documento" :error="uploadForm.errors.title" />
                <SelectInput id="request-item-upload-source" v-model="uploadForm.source" label="Origem" :options="sourceOptions" :error="uploadForm.errors.source" />
                <label class="grid gap-1 text-sm font-medium text-slate-700">
                    Arquivo
                    <input class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" type="file" @input="uploadForm.file = $event.target.files[0]" />
                    <span v-if="uploadForm.errors.file" class="text-xs font-medium text-red-600">{{ uploadForm.errors.file }}</span>
                </label>
            </form>
            <template #footer>
                <Button variant="secondary" @click="uploadModalOpen = false">Cancelar</Button>
                <Button type="submit" form="request-item-upload-form" :loading="uploadForm.processing">Enviar</Button>
            </template>
        </Modal>

        <Modal v-if="rejectModalOpen" open title="Recusar documento" @close="rejectModalOpen = false">
            <form id="request-item-reject-form" class="grid gap-4" @submit.prevent="submitReject">
                <TextareaInput id="request-item-rejection" v-model="rejectForm.rejection_reason" label="Motivo da recusa" required :error="rejectForm.errors.rejection_reason" />
            </form>
            <template #footer>
                <Button variant="secondary" @click="rejectModalOpen = false">Cancelar</Button>
                <Button type="submit" form="request-item-reject-form" variant="danger" :loading="rejectForm.processing">Recusar</Button>
            </template>
        </Modal>

        <Modal v-if="cancelModalOpen" open title="Cancelar solicitação" @close="cancelModalOpen = false">
            <form id="request-cancel-form" class="grid gap-4" @submit.prevent="submitCancel">
                <TextareaInput id="request-cancellation" v-model="cancelForm.cancellation_reason" label="Motivo do cancelamento" required :error="cancelForm.errors.cancellation_reason" />
            </form>
            <template #footer>
                <Button variant="secondary" @click="cancelModalOpen = false">Voltar</Button>
                <Button type="submit" form="request-cancel-form" variant="danger" :loading="cancelForm.processing">Cancelar solicitação</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
