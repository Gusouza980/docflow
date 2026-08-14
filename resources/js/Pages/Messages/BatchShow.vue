<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Button from '../../Components/UI/Button.vue';
import Card from '../../Components/UI/Card.vue';
import DataTable from '../../Components/Data/DataTable.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';

const props = defineProps({
    batch: { type: Object, required: true },
    messages: { type: Array, default: () => [] },
});

const page = usePage();

const columns = [
    { key: 'client_name', label: 'Cliente' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' },
];

function openWhatsApp(message) {
    if (message.whatsapp_url) {
        window.open(message.whatsapp_url, '_blank', 'noopener');
    }

    router.post(`/clients/${message.client_id}/messages/${message.id}/whatsapp`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Lote enviado" />
    <AppLayout
        title="Lote enviado"
        active-nav="message-batch"
        :breadcrumbs="[{ label: 'Envio em lote', href: '/messages/batch' }, { label: 'Lote' }]"
    >
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <Card :title="batch.template_name || 'Lote'">
                <p class="text-sm text-slate-600">
                    {{ batch.filter_label }} · {{ batch.channel_label }} · {{ batch.created_at }}
                </p>
                <p v-if="batch.skipped_count" class="mt-2 text-sm text-amber-800">
                    {{ batch.skipped_count }} destinatários pulados na revisão.
                </p>
            </Card>

            <DataTable :columns="columns" :rows="messages" empty-title="Nenhuma mensagem neste lote">
                <template #toolbar>
                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                        <h2 class="text-sm font-semibold text-slate-950">Destinatários</h2>
                        <Link href="/messages/batch" class="text-sm font-semibold underline">Novo lote</Link>
                    </div>
                </template>
                <template #cell-client_name="{ row }">
                    <Link :href="`/clients/${row.client_id}?tab=communication`" class="font-semibold text-slate-950 underline">
                        {{ row.client_name }}
                    </Link>
                    <p v-if="row.failure_reason" class="mt-1 text-xs text-red-700">{{ row.failure_reason }}</p>
                </template>
                <template #cell-status="{ row }">
                    <StatusPill :status="row.status" />
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end">
                        <Button v-if="row.can_open_whatsapp" size="sm" @click="openWhatsApp(row)">Abrir WhatsApp</Button>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
