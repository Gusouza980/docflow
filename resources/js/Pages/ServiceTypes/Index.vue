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
    serviceTypes: { type: Array, default: () => [] },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const createModalOpen = ref(false);
const editModalOpen = ref(false);
const selectedType = ref(null);

const columns = [
    { key: 'name', label: 'Serviço' },
    { key: 'defaults', label: 'Defaults' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' },
];

const createForm = useForm({
    name: '',
    description: '',
    is_active: true,
    default_amount_cents: '',
    default_billing_interval: 'month',
});

const editForm = useForm({
    name: '',
    description: '',
    is_active: true,
    default_amount_cents: '',
    default_billing_interval: 'month',
});

const money = (cents) => {
    if (cents === null || cents === undefined || cents === '') {
        return '—';
    }

    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(cents) / 100);
};

function submitCreate() {
    createForm.transform((data) => ({
        ...data,
        default_amount_cents: data.default_amount_cents === '' ? null : Number(data.default_amount_cents),
    })).post('/service-types', {
        preserveScroll: true,
        onSuccess: () => {
            createModalOpen.value = false;
            createForm.reset();
        },
    });
}

function openEdit(type) {
    selectedType.value = type;
    editForm.clearErrors();
    editForm.name = type.name;
    editForm.description = type.description ?? '';
    editForm.is_active = type.is_active;
    editForm.default_amount_cents = type.default_amount_cents ?? '';
    editForm.default_billing_interval = type.default_billing_interval ?? 'month';
    editModalOpen.value = true;
}

function submitEdit() {
    editForm.transform((data) => ({
        ...data,
        default_amount_cents: data.default_amount_cents === '' ? null : Number(data.default_amount_cents),
    })).patch(`/service-types/${selectedType.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editModalOpen.value = false;
        },
    });
}
</script>

<template>
    <Head title="Tipos de serviço" />
    <AppLayout title="Tipos de serviço" active-nav="service-types" :breadcrumbs="[{ label: 'Serviços' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <DataTable :columns="columns" :rows="serviceTypes" empty-title="Nenhum tipo de serviço">
                <template #toolbar>
                    <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-950">Catálogo de serviços</h2>
                            <p class="mt-1 text-xs text-slate-500">Defina os tipos que o escritório oferece aos clientes.</p>
                        </div>
                        <Button v-if="can.manage" size="sm" @click="createModalOpen = true">Novo tipo</Button>
                    </div>
                </template>
                <template #cell-name="{ row }">
                    <div>
                        <p class="font-semibold text-slate-950">{{ row.name }}</p>
                        <p v-if="row.description" class="mt-1 text-xs text-slate-500">{{ row.description }}</p>
                    </div>
                </template>
                <template #cell-defaults="{ row }">
                    <p class="text-sm text-slate-700">{{ money(row.default_amount_cents) }}</p>
                    <p class="text-xs text-slate-500">{{ row.default_billing_interval_label || 'Sem recorrência' }}</p>
                </template>
                <template #cell-status="{ row }">
                    <Badge :tone="row.is_active ? 'success' : 'secondary'">{{ row.is_active ? 'Ativo' : 'Inativo' }}</Badge>
                </template>
                <template #cell-actions="{ row }">
                    <div v-if="can.manage" class="flex justify-end">
                        <Button size="sm" variant="secondary" @click="openEdit(row)">Editar</Button>
                    </div>
                </template>
            </DataTable>
        </div>

        <Modal v-if="createModalOpen" open title="Novo tipo de serviço" @close="createModalOpen = false">
            <form class="grid gap-3" @submit.prevent="submitCreate">
                <TextInput id="service-type-name" v-model="createForm.name" label="Nome" required :error="createForm.errors.name" />
                <TextareaInput id="service-type-description" v-model="createForm.description" label="Descrição" :error="createForm.errors.description" />
                <TextInput id="service-type-amount" v-model="createForm.default_amount_cents" type="number" label="Valor sugerido (centavos)" :error="createForm.errors.default_amount_cents" />
                <SelectInput id="service-type-interval" v-model="createForm.default_billing_interval" label="Recorrência sugerida" :options="options.billing_intervals" :error="createForm.errors.default_billing_interval" />
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" @click="createModalOpen = false">Cancelar</Button>
                    <Button type="submit" :disabled="createForm.processing">Salvar</Button>
                </div>
            </form>
        </Modal>

        <Modal v-if="editModalOpen" open title="Editar tipo de serviço" @close="editModalOpen = false">
            <form class="grid gap-3" @submit.prevent="submitEdit">
                <TextInput id="edit-service-type-name" v-model="editForm.name" label="Nome" required :error="editForm.errors.name" />
                <TextareaInput id="edit-service-type-description" v-model="editForm.description" label="Descrição" :error="editForm.errors.description" />
                <TextInput id="edit-service-type-amount" v-model="editForm.default_amount_cents" type="number" label="Valor sugerido (centavos)" :error="editForm.errors.default_amount_cents" />
                <SelectInput id="edit-service-type-interval" v-model="editForm.default_billing_interval" label="Recorrência sugerida" :options="options.billing_intervals" :error="editForm.errors.default_billing_interval" />
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="editForm.is_active" type="checkbox" class="rounded border-slate-300" />
                    Ativo
                </label>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" @click="editModalOpen = false">Cancelar</Button>
                    <Button type="submit" :disabled="editForm.processing">Salvar</Button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
