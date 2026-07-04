<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import PlatformLayout from '../../../Layouts/PlatformLayout.vue';
import Alert from '../../../Components/Feedback/Alert.vue';
import DataTable from '../../../Components/Data/DataTable.vue';
import Button from '../../../Components/UI/Button.vue';
import SelectInput from '../../../Components/Forms/SelectInput.vue';
import TextInput from '../../../Components/Forms/TextInput.vue';
import StatusPill from '../../../Components/UI/StatusPill.vue';

const props = defineProps({
    organizations: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statusOptions: { type: Array, default: () => [] },
});

const page = usePage();

const filterForm = useForm({
    status: props.filters.status ?? '',
    search: props.filters.search ?? '',
});

const columns = [
    { key: 'name', label: 'Organização' },
    { key: 'status', label: 'Status' },
    { key: 'members_count', label: 'Membros' },
    { key: 'clients_count', label: 'Clientes' },
    { key: 'created_at', label: 'Criada em' },
    { key: 'actions', label: '' },
];

function applyFilters() {
    router.get('/platform/organizations', filterForm.data(), { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head title="Platform · Organizações" />
    <PlatformLayout title="Organizações (tenants)" active-nav="organizations" :breadcrumbs="[{ label: 'Organizações' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>

            <form class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[180px_1fr_auto]" @submit.prevent="applyFilters">
                <SelectInput id="filter-status" v-model="filterForm.status" label="Status" :options="statusOptions" />
                <TextInput id="filter-search" v-model="filterForm.search" label="Buscar" placeholder="Nome, documento ou e-mail" />
                <div class="flex items-end">
                    <Button type="submit">Filtrar</Button>
                </div>
            </form>

            <DataTable :columns="columns" :rows="organizations.data" empty-title="Nenhuma organização encontrada">
                <template #cell-name="{ row }">
                    <div>
                        <p class="font-semibold text-slate-950">{{ row.name }}</p>
                        <p v-if="row.document" class="mt-0.5 text-xs text-slate-500">{{ row.document }}</p>
                    </div>
                </template>
                <template #cell-status="{ row }"><StatusPill :status="row.status" /></template>
                <template #cell-actions="{ row }">
                    <Link :href="`/platform/organizations/${row.id}`" class="text-sm font-semibold text-violet-700 hover:text-violet-900">Detalhes</Link>
                </template>
            </DataTable>

            <div v-if="organizations.meta.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
                <p>Página {{ organizations.meta.current_page }} de {{ organizations.meta.last_page }} · {{ organizations.meta.total }} registros</p>
                <div class="flex gap-2">
                    <Link
                        v-if="organizations.meta.current_page > 1"
                        :href="`/platform/organizations?page=${organizations.meta.current_page - 1}`"
                        preserve-state
                        class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-50"
                    >Anterior</Link>
                    <Link
                        v-if="organizations.meta.current_page < organizations.meta.last_page"
                        :href="`/platform/organizations?page=${organizations.meta.current_page + 1}`"
                        preserve-state
                        class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-50"
                    >Próxima</Link>
                </div>
            </div>
        </div>
    </PlatformLayout>
</template>
