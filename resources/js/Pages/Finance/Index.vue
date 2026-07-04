<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import DataTable from '../../Components/Data/DataTable.vue';
import Pagination from '../../Components/Data/Pagination.vue';
import Modal from '../../Components/Overlays/Modal.vue';
import Button from '../../Components/UI/Button.vue';
import Badge from '../../Components/UI/Badge.vue';
import Card from '../../Components/UI/Card.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';
import DisplayDate from '../../Components/UI/DisplayDate.vue';
import TextareaInput from '../../Components/Forms/TextareaInput.vue';

const props = defineProps({
    metrics: { type: Object, required: true },
    receivables: { type: Object, required: true },
    payables: { type: Object, required: true },
    recurrences: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
});

const page = usePage();
const receivableModalOpen = ref(false);
const payableModalOpen = ref(false);
const categoryModalOpen = ref(false);
const recurrenceModalOpen = ref(false);
const paymentModalOpen = ref(false);
const cancelModalOpen = ref(false);
const renegotiateModalOpen = ref(false);
const reminderModalOpen = ref(false);
const paymentTarget = ref(null);
const paymentType = ref('receivable');
const withEmpty = (items, label = 'Todos') => [{ value: '', label }, ...items];
const money = (cents) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format((cents ?? 0) / 100);
const incomeCategories = computed(() => props.options.categories.filter((category) => ['income', 'both'].includes(category.type)));
const expenseCategories = computed(() => props.options.categories.filter((category) => ['expense', 'both'].includes(category.type)));

const statusOptions = [
    { value: '', label: 'Todos' },
    { value: 'open', label: 'Aberta' },
    { value: 'partial', label: 'Parcial' },
    { value: 'paid', label: 'Paga' },
    { value: 'cancelled', label: 'Cancelada' },
    { value: 'renegotiated', label: 'Renegociada' },
];
const reminderChannelOptions = [
    { value: 'email', label: 'E-mail' },
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'phone', label: 'Telefone' },
    { value: 'internal', label: 'Registro interno' },
];
const recurrenceColumns = [
    { key: 'description', label: 'Recorrência' },
    { key: 'amount', label: 'Valor' },
    { key: 'next_due_date', label: 'Próximo vencimento' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' },
];
const categoryTypeOptions = [
    { value: 'income', label: 'Receita' },
    { value: 'expense', label: 'Despesa' },
    { value: 'both', label: 'Ambas' },
];
const receivableColumns = [
    { key: 'description', label: 'Cobrança' },
    { key: 'status', label: 'Status' },
    { key: 'amount', label: 'Valor' },
    { key: 'due_at', label: 'Vencimento' },
    { key: 'actions', label: '' },
];
const payableColumns = [
    { key: 'description', label: 'Despesa' },
    { key: 'status', label: 'Status' },
    { key: 'amount', label: 'Valor' },
    { key: 'due_at', label: 'Vencimento' },
    { key: 'actions', label: '' },
];

const filterForm = useForm({
    status: props.filters.status ?? '',
    client_id: props.filters.client_id ?? '',
});
const receivableForm = useForm({
    client_id: '',
    financial_category_id: '',
    description: '',
    amount_cents: '',
    due_at: '',
    competence_date: '',
    notes: '',
});
const payableForm = useForm({
    client_id: '',
    financial_category_id: '',
    description: '',
    vendor_name: '',
    amount_cents: '',
    due_at: '',
    competence_date: '',
    is_reimbursable: false,
    notes: '',
});
const categoryForm = useForm({
    name: '',
    type: 'both',
    color: '#0f766e',
    is_active: true,
});
const paymentForm = useForm({
    amount_cents: '',
    paid_at: '',
    method: '',
    reference: '',
    notes: '',
});
const cancelForm = useForm({ cancellation_reason: '' });
const recurrenceForm = useForm({
    client_id: '',
    financial_category_id: '',
    description: '',
    amount_cents: '',
    billing_day: 10,
    start_date: new Date().toISOString().slice(0, 10),
    end_date: '',
    frequency: 'monthly',
    is_active: true,
    notes: '',
});
const renegotiateForm = useForm({
    renegotiation_reason: '',
    amount_cents: '',
    due_at: '',
    description: '',
    notes: '',
});
const reminderForm = useForm({
    channel: 'email',
    notes: '',
    notify_client: true,
});

function applyFilters() {
    router.get('/finance', filterForm.data(), { preserveState: true, preserveScroll: true });
}

function submitReceivable() {
    receivableForm.post('/finance/receivables', { preserveScroll: true, onSuccess: () => receivableModalOpen.value = false });
}

function submitPayable() {
    payableForm.post('/finance/payables', { preserveScroll: true, onSuccess: () => payableModalOpen.value = false });
}

function submitCategory() {
    categoryForm.post('/finance/categories', { preserveScroll: true, onSuccess: () => categoryModalOpen.value = false });
}

function openPayment(type, item) {
    paymentType.value = type;
    paymentTarget.value = item;
    paymentForm.reset();
    paymentForm.amount_cents = item.balance_cents;
    paymentForm.paid_at = new Date().toISOString().slice(0, 10);
    paymentModalOpen.value = true;
}

function submitPayment() {
    const url = paymentType.value === 'receivable'
        ? `/finance/receivables/${paymentTarget.value.id}/payments`
        : `/finance/payables/${paymentTarget.value.id}/payments`;

    paymentForm.post(url, { preserveScroll: true, onSuccess: () => paymentModalOpen.value = false });
}

function openCancel(receivable) {
    paymentTarget.value = receivable;
    cancelForm.reset();
    cancelModalOpen.value = true;
}

function submitCancel() {
    cancelForm.patch(`/finance/receivables/${paymentTarget.value.id}/cancel`, { preserveScroll: true, onSuccess: () => cancelModalOpen.value = false });
}

function submitRecurrence() {
    recurrenceForm.post('/finance/recurrences', { preserveScroll: true, onSuccess: () => recurrenceModalOpen.value = false });
}

function generateRecurrence(recurrence) {
    useForm({}).post(`/finance/recurrences/${recurrence.id}/generate`, { preserveScroll: true });
}

function openRenegotiate(receivable) {
    paymentTarget.value = receivable;
    renegotiateForm.reset();
    renegotiateForm.amount_cents = receivable.balance_cents;
    renegotiateForm.due_at = receivable.due_at;
    renegotiateForm.description = receivable.description;
    renegotiateModalOpen.value = true;
}

function submitRenegotiate() {
    renegotiateForm.patch(`/finance/receivables/${paymentTarget.value.id}/renegotiate`, {
        preserveScroll: true,
        onSuccess: () => renegotiateModalOpen.value = false,
    });
}

function openReminder(receivable) {
    paymentTarget.value = receivable;
    reminderForm.reset();
    reminderForm.channel = 'email';
    reminderModalOpen.value = true;
}

function submitReminder() {
    reminderForm.post(`/finance/receivables/${paymentTarget.value.id}/reminders`, {
        preserveScroll: true,
        onSuccess: () => reminderModalOpen.value = false,
    });
}
</script>

<template>
    <Head title="Financeiro" />
    <AppLayout title="Financeiro" active-nav="finance" :breadcrumbs="[{ label: 'Financeiro' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <div class="grid gap-4 md:grid-cols-4">
                <Card title="A receber"><p class="text-2xl font-semibold text-slate-950">{{ money(metrics.open_receivables_cents) }}</p></Card>
                <Card title="Inadimplência"><p class="text-2xl font-semibold text-red-700">{{ money(metrics.overdue_receivables_cents) }}</p></Card>
                <Card title="A pagar"><p class="text-2xl font-semibold text-slate-950">{{ money(metrics.open_payables_cents) }}</p></Card>
                <Card title="Saldo realizado"><p class="text-2xl font-semibold text-emerald-700">{{ money(metrics.cash_balance_cents) }}</p></Card>
            </div>

            <div class="flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:flex-row sm:items-end sm:justify-between">
                <form class="grid flex-1 gap-3 md:grid-cols-2" @submit.prevent="applyFilters">
                    <SelectInput id="finance-client-filter" v-model="filterForm.client_id" label="Cliente" :options="withEmpty(options.clients)" />
                    <SelectInput id="finance-status-filter" v-model="filterForm.status" label="Status" :options="statusOptions" />
                </form>
                <div class="flex flex-wrap gap-2">
                    <Button variant="secondary" @click="applyFilters">Filtrar</Button>
                    <Button variant="secondary" @click="categoryModalOpen = true">Categoria</Button>
                    <Button variant="secondary" @click="recurrenceModalOpen = true">Recorrência</Button>
                    <Button variant="secondary" @click="payableModalOpen = true">Conta a pagar</Button>
                    <Button @click="receivableModalOpen = true">Conta a receber</Button>
                </div>
            </div>

            <DataTable :columns="recurrenceColumns" :rows="recurrences" empty-title="Nenhuma recorrência cadastrada">
                <template #toolbar><div class="border-b border-slate-200 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Cobranças recorrentes</h2><p class="mt-1 text-xs text-slate-500">Geradas automaticamente todo dia às 06:00 ou manualmente abaixo.</p></div></template>
                <template #cell-description="{ row }"><div class="min-w-64"><p class="font-semibold text-slate-950">{{ row.description }}</p><p class="mt-1 text-xs text-slate-500">{{ row.client.name }} · dia {{ row.billing_day }}</p></div></template>
                <template #cell-amount="{ row }">{{ money(row.amount_cents) }}</template>
                <template #cell-next_due_date="{ row }"><DisplayDate :value="row.next_due_date" fallback="—" /></template>
                <template #cell-status="{ row }"><Badge :tone="row.is_active ? (row.is_due ? 'warning' : 'success') : 'secondary'">{{ row.is_active ? (row.is_due ? 'Pronta p/ gerar' : 'Ativa') : 'Inativa' }}</Badge></template>
                <template #cell-actions="{ row }"><div class="flex justify-end"><Button v-if="row.is_active" size="sm" variant="secondary" @click="generateRecurrence(row)">Gerar agora</Button></div></template>
            </DataTable>

            <DataTable :columns="receivableColumns" :rows="receivables.data" empty-title="Nenhuma cobrança encontrada">
                <template #toolbar><div class="border-b border-slate-200 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Contas a receber</h2></div></template>
                <template #cell-description="{ row }"><div class="min-w-64"><p class="font-semibold text-slate-950">{{ row.description }}</p><p class="mt-1 text-xs text-slate-500">{{ row.client.name }} · {{ row.category?.name ?? 'Sem categoria' }}</p><Badge v-if="row.reminders_count" tone="warning" class="mt-1">{{ row.reminders_count }} lembrete(s)</Badge></div></template>
                <template #cell-status="{ row }"><StatusPill :status="row.status" /></template>
                <template #cell-amount="{ row }"><span>{{ money(row.balance_cents) }}</span><span class="block text-xs text-slate-500">de {{ money(row.amount_cents) }}</span></template>
                <template #cell-due_at="{ row }"><span :class="row.is_overdue ? 'font-semibold text-red-700' : ''"><DisplayDate :value="row.due_at" fallback="Sem prazo" /></span></template>
                <template #cell-actions="{ row }"><div class="flex flex-wrap justify-end gap-2"><Button v-if="row.can_renegotiate" size="sm" variant="secondary" @click="openRenegotiate(row)">Renegociar</Button><Button v-if="row.is_overdue" size="sm" variant="secondary" @click="openReminder(row)">Lembrete</Button><Button v-if="!['paid', 'cancelled', 'renegotiated'].includes(row.status)" size="sm" variant="secondary" @click="openPayment('receivable', row)">Baixar</Button><Button v-if="!['paid', 'cancelled', 'renegotiated'].includes(row.status)" size="sm" variant="danger" @click="openCancel(row)">Cancelar</Button></div></template>
            </DataTable>
            <Pagination :current-page="receivables.meta.current_page" :total-pages="receivables.meta.last_page" :per-page="receivables.meta.per_page" />

            <DataTable :columns="payableColumns" :rows="payables.data" empty-title="Nenhuma despesa encontrada">
                <template #toolbar><div class="border-b border-slate-200 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Contas a pagar</h2></div></template>
                <template #cell-description="{ row }"><div class="min-w-64"><p class="font-semibold text-slate-950">{{ row.description }}</p><p class="mt-1 text-xs text-slate-500">{{ row.vendor_name || 'Sem fornecedor' }} · {{ row.client?.name ?? 'Escritório' }}</p><Badge v-if="row.is_reimbursable" tone="warning">Reembolsável</Badge></div></template>
                <template #cell-status="{ row }"><StatusPill :status="row.status" /></template>
                <template #cell-amount="{ row }"><span>{{ money(row.balance_cents) }}</span><span class="block text-xs text-slate-500">de {{ money(row.amount_cents) }}</span></template>
                <template #cell-due_at="{ row }"><span :class="row.is_overdue ? 'font-semibold text-red-700' : ''"><DisplayDate :value="row.due_at" fallback="Sem prazo" /></span></template>
                <template #cell-actions="{ row }"><div class="flex justify-end"><Button v-if="row.status !== 'paid'" size="sm" variant="secondary" @click="openPayment('payable', row)">Pagar</Button></div></template>
            </DataTable>
        </div>

        <Modal v-if="receivableModalOpen" open title="Conta a receber" @close="receivableModalOpen = false">
            <form id="receivable-form" class="grid gap-4" @submit.prevent="submitReceivable">
                <SelectInput id="receivable-client" v-model="receivableForm.client_id" label="Cliente" :options="withEmpty(options.clients, 'Selecione')" :error="receivableForm.errors.client_id" />
                <SelectInput id="receivable-category" v-model="receivableForm.financial_category_id" label="Categoria" :options="withEmpty(incomeCategories, 'Sem categoria')" :error="receivableForm.errors.financial_category_id" />
                <TextInput id="receivable-description" v-model="receivableForm.description" label="Descrição" required :error="receivableForm.errors.description" />
                <div class="grid gap-4 sm:grid-cols-3"><TextInput id="receivable-amount" v-model="receivableForm.amount_cents" type="number" label="Valor em centavos" required :error="receivableForm.errors.amount_cents" /><TextInput id="receivable-due" v-model="receivableForm.due_at" type="date" label="Vencimento" required :error="receivableForm.errors.due_at" /><TextInput id="receivable-competence" v-model="receivableForm.competence_date" type="date" label="Competência" :error="receivableForm.errors.competence_date" /></div>
                <TextareaInput id="receivable-notes" v-model="receivableForm.notes" label="Observações" :error="receivableForm.errors.notes" />
            </form>
            <template #actions><Button type="submit" form="receivable-form" :loading="receivableForm.processing">Salvar</Button></template>
        </Modal>

        <Modal v-if="payableModalOpen" open title="Conta a pagar" @close="payableModalOpen = false">
            <form id="payable-form" class="grid gap-4" @submit.prevent="submitPayable">
                <SelectInput id="payable-client" v-model="payableForm.client_id" label="Cliente" :options="withEmpty(options.clients, 'Escritório')" :error="payableForm.errors.client_id" />
                <SelectInput id="payable-category" v-model="payableForm.financial_category_id" label="Categoria" :options="withEmpty(expenseCategories, 'Sem categoria')" :error="payableForm.errors.financial_category_id" />
                <TextInput id="payable-description" v-model="payableForm.description" label="Descrição" required :error="payableForm.errors.description" />
                <TextInput id="payable-vendor" v-model="payableForm.vendor_name" label="Fornecedor" :error="payableForm.errors.vendor_name" />
                <div class="grid gap-4 sm:grid-cols-3"><TextInput id="payable-amount" v-model="payableForm.amount_cents" type="number" label="Valor em centavos" required :error="payableForm.errors.amount_cents" /><TextInput id="payable-due" v-model="payableForm.due_at" type="date" label="Vencimento" required :error="payableForm.errors.due_at" /><TextInput id="payable-competence" v-model="payableForm.competence_date" type="date" label="Competência" :error="payableForm.errors.competence_date" /></div>
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700"><input v-model="payableForm.is_reimbursable" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-300" />Reembolsável ao cliente</label>
            </form>
            <template #actions><Button type="submit" form="payable-form" :loading="payableForm.processing">Salvar</Button></template>
        </Modal>

        <Modal v-if="categoryModalOpen" open title="Categoria financeira" @close="categoryModalOpen = false">
            <form id="category-form" class="grid gap-4" @submit.prevent="submitCategory">
                <TextInput id="category-name" v-model="categoryForm.name" label="Nome" required :error="categoryForm.errors.name" />
                <SelectInput id="category-type" v-model="categoryForm.type" label="Tipo" :options="categoryTypeOptions" :error="categoryForm.errors.type" />
            </form>
            <template #actions><Button type="submit" form="category-form" :loading="categoryForm.processing">Salvar</Button></template>
        </Modal>

        <Modal v-if="paymentModalOpen" open :title="paymentType === 'receivable' ? 'Registrar recebimento' : 'Registrar pagamento'" @close="paymentModalOpen = false">
            <form id="payment-form" class="grid gap-4" @submit.prevent="submitPayment">
                <TextInput id="payment-amount" v-model="paymentForm.amount_cents" type="number" label="Valor em centavos" required :error="paymentForm.errors.amount_cents" />
                <TextInput id="payment-date" v-model="paymentForm.paid_at" type="date" label="Data" required :error="paymentForm.errors.paid_at" />
                <TextInput id="payment-method" v-model="paymentForm.method" label="Método" :error="paymentForm.errors.method" />
                <TextareaInput id="payment-notes" v-model="paymentForm.notes" label="Observações" :error="paymentForm.errors.notes" />
            </form>
            <template #actions><Button type="submit" form="payment-form" :loading="paymentForm.processing">Registrar</Button></template>
        </Modal>

        <Modal v-if="cancelModalOpen" open title="Cancelar cobrança" @close="cancelModalOpen = false">
            <form id="cancel-form" class="grid gap-4" @submit.prevent="submitCancel">
                <TextareaInput id="cancel-reason" v-model="cancelForm.cancellation_reason" label="Motivo" required :error="cancelForm.errors.cancellation_reason" />
            </form>
            <template #actions><Button type="submit" form="cancel-form" variant="danger" :loading="cancelForm.processing">Cancelar cobrança</Button></template>
        </Modal>

        <Modal v-if="recurrenceModalOpen" open title="Cobrança recorrente" @close="recurrenceModalOpen = false">
            <form id="recurrence-form" class="grid gap-4" @submit.prevent="submitRecurrence">
                <SelectInput id="recurrence-client" v-model="recurrenceForm.client_id" label="Cliente" :options="withEmpty(options.clients, 'Selecione')" :error="recurrenceForm.errors.client_id" />
                <SelectInput id="recurrence-category" v-model="recurrenceForm.financial_category_id" label="Categoria" :options="withEmpty(incomeCategories, 'Sem categoria')" :error="recurrenceForm.errors.financial_category_id" />
                <TextInput id="recurrence-description" v-model="recurrenceForm.description" label="Descrição" required :error="recurrenceForm.errors.description" />
                <div class="grid gap-4 sm:grid-cols-3">
                    <TextInput id="recurrence-amount" v-model="recurrenceForm.amount_cents" type="number" label="Valor em centavos" required :error="recurrenceForm.errors.amount_cents" />
                    <TextInput id="recurrence-billing-day" v-model="recurrenceForm.billing_day" type="number" min="1" max="28" label="Dia de vencimento" required :error="recurrenceForm.errors.billing_day" />
                    <TextInput id="recurrence-start" v-model="recurrenceForm.start_date" type="date" label="Início" required :error="recurrenceForm.errors.start_date" />
                </div>
                <TextInput id="recurrence-end" v-model="recurrenceForm.end_date" type="date" label="Término (opcional)" :error="recurrenceForm.errors.end_date" />
                <TextareaInput id="recurrence-notes" v-model="recurrenceForm.notes" label="Observações" :error="recurrenceForm.errors.notes" />
            </form>
            <template #footer>
                <Button variant="secondary" @click="recurrenceModalOpen = false">Cancelar</Button>
                <Button type="submit" form="recurrence-form" :loading="recurrenceForm.processing">Salvar</Button>
            </template>
        </Modal>

        <Modal v-if="renegotiateModalOpen" open title="Renegociar cobrança" @close="renegotiateModalOpen = false">
            <form id="renegotiate-form" class="grid gap-4" @submit.prevent="submitRenegotiate">
                <TextareaInput id="renegotiate-reason" v-model="renegotiateForm.renegotiation_reason" label="Motivo da renegociação" required :error="renegotiateForm.errors.renegotiation_reason" />
                <TextInput id="renegotiate-description" v-model="renegotiateForm.description" label="Descrição da nova cobrança" :error="renegotiateForm.errors.description" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <TextInput id="renegotiate-amount" v-model="renegotiateForm.amount_cents" type="number" label="Novo valor em centavos" required :error="renegotiateForm.errors.amount_cents" />
                    <TextInput id="renegotiate-due" v-model="renegotiateForm.due_at" type="date" label="Novo vencimento" required :error="renegotiateForm.errors.due_at" />
                </div>
                <TextareaInput id="renegotiate-notes" v-model="renegotiateForm.notes" label="Observações" :error="renegotiateForm.errors.notes" />
            </form>
            <template #footer>
                <Button variant="secondary" @click="renegotiateModalOpen = false">Cancelar</Button>
                <Button type="submit" form="renegotiate-form" :loading="renegotiateForm.processing">Renegociar</Button>
            </template>
        </Modal>

        <Modal v-if="reminderModalOpen" open title="Registrar lembrete de cobrança" @close="reminderModalOpen = false">
            <form id="reminder-form" class="grid gap-4" @submit.prevent="submitReminder">
                <SelectInput id="reminder-channel" v-model="reminderForm.channel" label="Canal" :options="reminderChannelOptions" :error="reminderForm.errors.channel" />
                <TextareaInput id="reminder-notes" v-model="reminderForm.notes" label="Observações" :error="reminderForm.errors.notes" />
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input v-model="reminderForm.notify_client" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-300" />
                    Notificar cliente no portal (alerta + e-mail)
                </label>
            </form>
            <template #footer>
                <Button variant="secondary" @click="reminderModalOpen = false">Cancelar</Button>
                <Button type="submit" form="reminder-form" :loading="reminderForm.processing">Registrar lembrete</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
