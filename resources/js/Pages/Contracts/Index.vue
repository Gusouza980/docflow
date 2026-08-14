<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import DataTable from '../../Components/Data/DataTable.vue';
import Modal from '../../Components/Overlays/Modal.vue';
import Button from '../../Components/UI/Button.vue';
import Badge from '../../Components/UI/Badge.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import CurrencyInput from '../../Components/Forms/CurrencyInput.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';
import CheckboxInput from '../../Components/Forms/CheckboxInput.vue';
import TextareaInput from '../../Components/Forms/TextareaInput.vue';
import { formatBrlCurrency } from '../../lib/money';

const props = defineProps({
    contracts: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const createModalOpen = ref(false);

const columns = [
    { key: 'code', label: 'Contrato' },
    { key: 'client_name', label: 'Cliente' },
    { key: 'status', label: 'Status' },
    { key: 'period', label: 'Vigência' },
    { key: 'actions', label: '' },
];

const filterForm = useForm({
    status: props.filters.status ?? '',
    expiring_soon: props.filters.expiring_soon ? 1 : '',
});

const createForm = useForm({
    client_id: '',
    code: '',
    status: 'active',
    amount_cents: '',
    billing_interval: 'month',
    starts_at: '',
    ends_at: '',
    auto_renew: false,
    scope_included: '',
    scope_excluded: '',
    create_receivable_recurrence: false,
});

const money = formatBrlCurrency;

function applyFilters() {
    filterForm.get('/contracts', { preserveState: true, preserveScroll: true });
}

function submitCreate() {
    createForm.transform((data) => ({
        ...data,
        amount_cents: data.amount_cents === '' ? null : data.amount_cents,
        ends_at: data.ends_at || null,
    })).post('/contracts', {
        preserveScroll: true,
        onSuccess: () => {
            createModalOpen.value = false;
        },
    });
}

const statusTone = (status) => {
    if (status === 'active') return 'success';
    if (status === 'expired' || status === 'canceled') return 'danger';
    return 'secondary';
};
</script>

<template>
    <Head title="Contratos" />
    <AppLayout title="Contratos" active-nav="contracts" :breadcrumbs="[{ label: 'Contratos' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <DataTable :columns="columns" :rows="contracts" empty-title="Nenhum contrato encontrado">
                <template #toolbar>
                    <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-sm font-semibold text-slate-950">Contratos</h2>
                                <p class="mt-1 text-xs text-slate-500">Vigência, renovação e escopo por cliente.</p>
                            </div>
                            <Button v-if="can.create" size="sm" @click="createModalOpen = true">Novo contrato</Button>
                        </div>
                        <form class="grid gap-2 sm:grid-cols-[1fr_auto_auto]" @submit.prevent="applyFilters">
                            <SelectInput
                                id="contracts-status"
                                v-model="filterForm.status"
                                label="Status"
                                :options="[{ value: '', label: 'Todos' }, ...options.statuses]"
                            />
                            <SelectInput
                                id="contracts-expiring"
                                v-model="filterForm.expiring_soon"
                                label="Vencimento"
                                :options="[{ value: '', label: 'Qualquer' }, { value: 1, label: 'Vence em 30 dias' }]"
                            />
                            <div class="flex items-end">
                                <Button type="submit" variant="secondary" size="sm">Filtrar</Button>
                            </div>
                        </form>
                    </div>
                </template>
                <template #cell-code="{ row }">
                    <div>
                        <p class="font-semibold text-slate-950">{{ row.code }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ money(row.amount_cents) }} · {{ row.billing_interval_label }}</p>
                    </div>
                </template>
                <template #cell-status="{ row }">
                    <Badge :tone="row.is_expiring_soon ? 'warning' : statusTone(row.status)">
                        {{ row.is_expiring_soon ? 'A vencer' : row.status_label }}
                    </Badge>
                </template>
                <template #cell-period="{ row }">
                    <p class="text-sm text-slate-700">{{ row.starts_at || '—' }} → {{ row.ends_at || 'sem término' }}</p>
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end">
                        <Link :href="row.href" class="text-sm font-semibold text-slate-800 underline">Abrir</Link>
                    </div>
                </template>
            </DataTable>
        </div>

        <Modal v-if="createModalOpen" open title="Novo contrato" @close="createModalOpen = false">
            <form class="grid gap-3" @submit.prevent="submitCreate">
                <SelectInput id="contract-client" v-model="createForm.client_id" label="Cliente" :options="options.clients" required :error="createForm.errors.client_id" />
                <TextInput id="contract-code" v-model="createForm.code" label="Código" required :error="createForm.errors.code" />
                <SelectInput id="contract-status" v-model="createForm.status" label="Status" :options="options.statuses.filter((item) => ['draft', 'active'].includes(item.value))" />
                <CurrencyInput id="contract-amount" v-model="createForm.amount_cents" label="Valor" :error="createForm.errors.amount_cents" />
                <SelectInput id="contract-interval" v-model="createForm.billing_interval" label="Recorrência" :options="options.billing_intervals" :error="createForm.errors.billing_interval" />
                <TextInput id="contract-starts" v-model="createForm.starts_at" type="date" label="Início" required :error="createForm.errors.starts_at" />
                <TextInput id="contract-ends" v-model="createForm.ends_at" type="date" label="Término" :error="createForm.errors.ends_at" />
                <CheckboxInput
                    v-if="can.manage && ['month', 'year'].includes(createForm.billing_interval) && createForm.status === 'active'"
                    v-model="createForm.create_receivable_recurrence"
                    label="Gerar mensalidade no financeiro"
                />
                <p
                    v-if="can.manage && ['month', 'year'].includes(createForm.billing_interval) && createForm.status === 'active'"
                    class="text-xs text-slate-500"
                >
                    Cria uma cobrança recorrente ligada a este contrato. Pode pausar depois no financeiro ou ao cancelar o contrato.
                </p>
                <TextareaInput
                    id="contract-scope-in"
                    v-model="createForm.scope_included"
                    label="O que está incluso"
                    hint="Descreva entregas, volume e frequência cobertos por este contrato."
                    placeholder="Ex.: BPO contábil mensal, até 50 notas fiscais, envio de guias até o dia 10."
                    :error="createForm.errors.scope_included"
                />
                <TextareaInput
                    id="contract-scope-out"
                    v-model="createForm.scope_excluded"
                    label="O que não está incluso"
                    hint="Registre limites e itens que ficam de fora para evitar dúvida com o cliente."
                    placeholder="Ex.: Folha de pagamento, auditoria e consultoria tributária sob demanda."
                    :error="createForm.errors.scope_excluded"
                />
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" @click="createModalOpen = false">Cancelar</Button>
                    <Button type="submit" :disabled="createForm.processing">Salvar</Button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
