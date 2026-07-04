<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import PlatformLayout from '../../../Layouts/PlatformLayout.vue';
import Alert from '../../../Components/Feedback/Alert.vue';
import DataTable from '../../../Components/Data/DataTable.vue';
import Button from '../../../Components/UI/Button.vue';
import Badge from '../../../Components/UI/Badge.vue';

defineProps({
    plans: { type: Array, default: () => [] },
});

const page = usePage();
const money = (cents) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format((cents ?? 0) / 100);

const columns = [
    { key: 'name', label: 'Plano' },
    { key: 'price_cents', label: 'Preço' },
    { key: 'status', label: 'Status' },
    { key: 'organizations_count', label: 'Organizações' },
    { key: 'actions', label: '' },
];
</script>

<template>
    <Head title="Platform · Planos" />
    <PlatformLayout title="Planos comerciais" active-nav="plans" :breadcrumbs="[{ label: 'Planos' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <div class="flex justify-end">
                <Link href="/platform/plans/create"><Button>Novo plano</Button></Link>
            </div>
            <DataTable :columns="columns" :rows="plans" empty-title="Nenhum plano cadastrado">
                <template #cell-name="{ row }">
                    <div>
                        <p class="font-semibold text-slate-950">{{ row.name }}</p>
                        <p class="text-xs text-slate-500">{{ row.slug }}</p>
                    </div>
                </template>
                <template #cell-price_cents="{ row }">{{ money(row.price_cents) }}</template>
                <template #cell-status="{ row }">
                    <Badge :tone="row.is_active ? 'success' : 'neutral'">{{ row.is_active ? 'Ativo' : 'Inativo' }}</Badge>
                </template>
                <template #cell-actions="{ row }">
                    <Link :href="`/platform/plans/${row.id}/edit`" class="text-sm font-semibold text-violet-700">Editar</Link>
                </template>
            </DataTable>
        </div>
    </PlatformLayout>
</template>
