<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import DataTable from '../../Components/Data/DataTable.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';
import Badge from '../../Components/UI/Badge.vue';

defineProps({
    sections: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({}) },
    can_access_finance: { type: Boolean, default: false },
});

const page = usePage();

const columns = [
    { key: 'title', label: 'Pendência' },
    { key: 'client_name', label: 'Cliente' },
    { key: 'due_at', label: 'Prazo' },
    { key: 'status', label: 'Status' },
];
</script>

<template>
    <Head title="Meu dia" />
    <AppLayout title="Meu dia" active-nav="my-day" :breadcrumbs="[{ label: 'Meu dia' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <p class="text-sm text-slate-600">
                {{ counts.total || 0 }} pendência(s) no seu escopo — tarefas, documentos, cobranças e chamados.
            </p>

            <p v-if="!counts.total" class="rounded-lg border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500">
                Nada para hoje.
            </p>

            <DataTable
                v-for="section in sections.filter((item) => item.items.length)"
                :key="section.key"
                :columns="columns"
                :rows="section.items"
                empty-title=""
            >
                <template #toolbar>
                    <div class="border-b border-slate-200 px-4 py-3">
                        <h2 class="text-sm font-semibold text-slate-950">{{ section.label }} ({{ section.items.length }})</h2>
                    </div>
                </template>
                <template #cell-title="{ row }">
                    <Link :href="row.href" class="font-semibold text-slate-950 underline">{{ row.title }}</Link>
                </template>
                <template #cell-due_at="{ row }">
                    <div class="flex items-center gap-2">
                        <span>{{ row.due_at || '—' }}</span>
                        <Badge v-if="row.overdue" tone="danger">Atrasado</Badge>
                    </div>
                </template>
                <template #cell-status="{ row }">
                    <StatusPill :status="row.status" />
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
