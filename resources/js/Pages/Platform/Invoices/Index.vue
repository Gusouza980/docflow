<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PlatformLayout from '../../../Layouts/PlatformLayout.vue';
import Alert from '../../../Components/Feedback/Alert.vue';
import DataTable from '../../../Components/Data/DataTable.vue';
import Button from '../../../Components/UI/Button.vue';
import Badge from '../../../Components/UI/Badge.vue';

defineProps({
    invoices: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ status: '', overdue: false }) },
});

const page = usePage();
const money = (cents) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format((cents ?? 0) / 100);

const columns = [
    { key: 'organization', label: 'Organização' },
    { key: 'plan_name', label: 'Plano' },
    { key: 'amount_cents', label: 'Valor' },
    { key: 'status', label: 'Status' },
    { key: 'due_at', label: 'Vencimento' },
    { key: 'actions', label: '' },
];

function applyFilter(overdue = false, status = '') {
    router.get('/platform/invoices', { overdue: overdue ? 1 : undefined, status: status || undefined }, { preserveState: true });
}
</script>

<template>
    <Head title="Platform · Faturas" />
    <PlatformLayout title="Faturas SaaS" active-nav="invoices" :breadcrumbs="[{ label: 'Faturas' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <div class="flex flex-wrap gap-2">
                <Button size="sm" variant="secondary" @click="applyFilter(false, '')">Todas</Button>
                <Button size="sm" variant="secondary" @click="applyFilter(false, 'open')">Em aberto</Button>
                <Button size="sm" variant="secondary" @click="applyFilter(true)">Vencidas</Button>
            </div>
            <DataTable :columns="columns" :rows="invoices" empty-title="Nenhuma fatura encontrada">
                <template #cell-organization="{ row }">
                    <Link :href="`/platform/organizations/${row.organization.id}`" class="font-semibold text-violet-700">{{ row.organization.name }}</Link>
                </template>
                <template #cell-amount_cents="{ row }">{{ money(row.amount_cents) }}</template>
                <template #cell-status="{ row }">
                    <Badge :tone="row.status === 'paid' ? 'success' : row.is_overdue ? 'danger' : 'warning'">{{ row.status }}</Badge>
                </template>
                <template #cell-actions="{ row }">
                    <div v-if="row.status === 'open'" class="flex gap-2">
                        <Link :href="`/platform/invoices/${row.id}/mark-paid`" method="post" as="button" class="text-sm font-semibold text-emerald-700">Marcar paga</Link>
                        <Link :href="`/platform/invoices/${row.id}/void`" method="post" as="button" class="text-sm font-semibold text-slate-600">Anular</Link>
                    </div>
                </template>
            </DataTable>
        </div>
    </PlatformLayout>
</template>
