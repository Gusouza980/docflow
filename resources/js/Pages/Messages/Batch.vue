<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Button from '../../Components/UI/Button.vue';
import Card from '../../Components/UI/Card.vue';
import DataTable from '../../Components/Data/DataTable.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';
import CheckboxInput from '../../Components/Forms/CheckboxInput.vue';

const props = defineProps({
    filters: { type: Object, required: true },
    preview: { type: Object, default: null },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();

const previewForm = useForm({
    filter: props.filters.filter ?? 'overdue',
    message_template_id: props.filters.message_template_id ?? '',
    client_ids: [...(props.filters.client_ids ?? [])],
});

const sendForm = useForm({
    filter: props.filters.filter ?? 'overdue',
    message_template_id: props.filters.message_template_id ?? '',
    client_ids: [...(props.filters.client_ids ?? [])],
});

const readyColumns = [
    { key: 'display_name', label: 'Cliente' },
    { key: 'destination', label: 'Destino' },
    { key: 'preview', label: 'Prévia' },
];

const skippedColumns = [
    { key: 'display_name', label: 'Cliente' },
    { key: 'reason', label: 'Motivo' },
];

function syncSendForm() {
    sendForm.filter = previewForm.filter;
    sendForm.message_template_id = previewForm.message_template_id;
    sendForm.client_ids = [...previewForm.client_ids];
}

function toggleClient(clientId, checked) {
    const id = Number(clientId);
    const next = new Set(previewForm.client_ids.map(Number));

    if (checked) {
        next.add(id);
    } else {
        next.delete(id);
    }

    previewForm.client_ids = [...next];
    syncSendForm();
}

function reviewRecipients() {
    syncSendForm();
    previewForm.get('/messages/batch', { preserveScroll: true });
}

function sendBatch() {
    syncSendForm();
    sendForm.post('/messages/batch');
}
</script>

<template>
    <Head title="Envio em lote" />
    <AppLayout
        title="Envio em lote"
        active-nav="message-batch"
        :breadcrumbs="[{ label: 'Portal', href: '/portal' }, { label: 'Envio em lote' }]"
    >
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <Card title="Quem recebe">
                <p class="mb-4 text-sm text-slate-600">
                    Revise a lista antes de enviar. Quem está sem consentimento ou sem contato some da fila — não do cadastro.
                </p>
                <form class="grid gap-4" @submit.prevent="reviewRecipients">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <SelectInput
                            id="batch-filter"
                            v-model="previewForm.filter"
                            label="Filtro"
                            :options="options.filters"
                            required
                            :error="previewForm.errors.filter || sendForm.errors.filter"
                        />
                        <SelectInput
                            id="batch-template"
                            v-model="previewForm.message_template_id"
                            label="Modelo"
                            :options="options.templates"
                            required
                            :error="previewForm.errors.message_template_id || sendForm.errors.message_template_id"
                        />
                    </div>
                    <div v-if="previewForm.filter === 'selected'" class="max-h-64 overflow-y-auto rounded-lg border border-slate-200 p-3">
                        <p class="mb-2 text-sm font-medium text-slate-700">Clientes</p>
                        <div class="grid gap-2">
                            <CheckboxInput
                                v-for="client in options.clients"
                                :key="client.value"
                                :model-value="previewForm.client_ids.map(Number).includes(Number(client.value))"
                                :label="client.label"
                                @update:model-value="(checked) => toggleClient(client.value, checked)"
                            />
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <Button type="submit" variant="secondary" :disabled="previewForm.processing">Revisar destinatários</Button>
                    </div>
                </form>
            </Card>

            <template v-if="preview">
                <DataTable :columns="readyColumns" :rows="preview.ready" empty-title="Ninguém pronto para envio">
                    <template #toolbar>
                        <div class="border-b border-slate-200 px-4 py-3">
                            <h2 class="text-sm font-semibold text-slate-950">Vão receber ({{ preview.ready.length }})</h2>
                        </div>
                    </template>
                    <template #cell-display_name="{ row }">
                        <p class="font-semibold text-slate-950">{{ row.display_name }}</p>
                    </template>
                    <template #cell-destination="{ row }">
                        <p class="text-sm text-slate-700">{{ row.destination || '—' }}</p>
                    </template>
                    <template #cell-preview="{ row }">
                        <p class="text-xs text-slate-500">{{ row.preview }}</p>
                    </template>
                </DataTable>

                <DataTable v-if="preview.skipped.length" :columns="skippedColumns" :rows="preview.skipped" empty-title="">
                    <template #toolbar>
                        <div class="border-b border-slate-200 px-4 py-3">
                            <h2 class="text-sm font-semibold text-slate-950">Serão pulados ({{ preview.skipped.length }})</h2>
                        </div>
                    </template>
                    <template #cell-reason="{ row }">
                        <p class="text-sm text-amber-800">{{ row.reason }}</p>
                    </template>
                </DataTable>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <Link href="/message-templates" class="text-sm font-semibold underline">Editar modelos</Link>
                    <Button
                        v-if="can.send"
                        :disabled="sendForm.processing || !preview.ready.length"
                        @click="sendBatch"
                    >
                        Enviar para {{ preview.ready.length }}
                    </Button>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
