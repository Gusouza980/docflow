<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ClientPortalLayout from '../../../Layouts/ClientPortalLayout.vue';
import Alert from '../../../Components/Feedback/Alert.vue';
import Badge from '../../../Components/UI/Badge.vue';
import Button from '../../../Components/UI/Button.vue';
import StatusPill from '../../../Components/UI/StatusPill.vue';
import TextInput from '../../../Components/Forms/TextInput.vue';

const props = defineProps({
    documentRequest: { type: Object, required: true },
});

const uploadingItemId = ref(null);
const uploadForm = useForm({
    title: '',
    file: null,
});

function startUpload(item) {
    uploadingItemId.value = item.id;
    uploadForm.title = item.title;
    uploadForm.file = null;
}

function cancelUpload() {
    uploadingItemId.value = null;
    uploadForm.reset();
}

function onFileChange(event) {
    uploadForm.file = event.target.files?.[0] ?? null;
}

function submitUpload(item) {
    uploadForm.post(`/client-portal/document-items/${item.id}/upload`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => cancelUpload(),
    });
}
</script>

<template>
    <Head :title="`${documentRequest.title} · Documentos`" />
    <ClientPortalLayout title="Enviar documentos" active-nav="documents">
        <div class="grid gap-6">
            <Link href="/client-portal/documents" class="text-sm font-medium text-emerald-700 hover:underline">← Voltar para solicitações</Link>

            <section class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-950">{{ documentRequest.title }}</h2>
                        <p class="mt-1 text-sm text-slate-500">Prazo geral: {{ documentRequest.due_at || 'Sem prazo' }}</p>
                        <p class="mt-2 text-sm text-slate-600">{{ documentRequest.received_items_count }} de {{ documentRequest.items_count }} itens enviados</p>
                    </div>
                    <StatusPill :status="documentRequest.status" />
                </div>
            </section>

            <div class="grid gap-4">
                <article
                    v-for="item in documentRequest.items"
                    :key="item.id"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-base font-semibold text-slate-950">{{ item.title }}</h3>
                        <StatusPill :status="item.status" />
                        <Badge v-if="item.category" tone="secondary">{{ item.category.name }}</Badge>
                    </div>

                    <p class="mt-3 text-sm leading-6 text-slate-600">{{ item.instructions || 'Sem instruções específicas.' }}</p>
                    <p class="mt-2 text-sm text-slate-700">Prazo: {{ item.due_at || documentRequest.due_at || 'Sem prazo' }}</p>

                    <Alert v-if="item.rejection_reason" class="mt-4" tone="danger">
                        Documento recusado: {{ item.rejection_reason }}
                    </Alert>

                    <p v-if="item.uploaded_file_name" class="mt-4 text-sm text-emerald-800">
                        Arquivo enviado: <span class="font-medium">{{ item.uploaded_file_name }}</span>
                    </p>

                    <div v-if="item.can_upload" class="mt-5 border-t border-slate-100 pt-5">
                        <div v-if="uploadingItemId !== item.id">
                            <Button size="sm" @click="startUpload(item)">
                                {{ item.status === 'rejected' ? 'Reenviar documento' : 'Enviar documento' }}
                            </Button>
                        </div>

                        <form v-else class="grid gap-4" @submit.prevent="submitUpload(item)">
                            <TextInput id="upload-title" v-model="uploadForm.title" label="Nome do documento" :error="uploadForm.errors.title" />
                            <label class="grid gap-2">
                                <span class="text-sm font-medium text-slate-900">Arquivo</span>
                                <input
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                                    class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-emerald-800 hover:file:bg-emerald-100"
                                    @change="onFileChange"
                                />
                                <span v-if="uploadForm.errors.file" class="text-xs font-medium text-red-600">{{ uploadForm.errors.file }}</span>
                            </label>
                            <p class="text-xs text-slate-500">Formatos: PDF, imagens, Word e Excel. Máximo 10 MB.</p>
                            <div class="flex flex-wrap gap-2">
                                <Button type="submit" :loading="uploadForm.processing" :disabled="!uploadForm.file">Confirmar envio</Button>
                                <Button type="button" variant="secondary" @click="cancelUpload">Cancelar</Button>
                            </div>
                        </form>
                    </div>
                </article>
            </div>
        </div>
    </ClientPortalLayout>
</template>
