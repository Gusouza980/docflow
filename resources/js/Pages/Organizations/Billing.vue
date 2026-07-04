<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Card from '../../Components/UI/Card.vue';
import Button from '../../Components/UI/Button.vue';
import Badge from '../../Components/UI/Badge.vue';
import Modal from '../../Components/Overlays/Modal.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    subscription: { type: Object, default: null },
    invoices: { type: Array, default: () => [] },
    nextOpenInvoice: { type: Object, default: null },
    lastPaidInvoice: { type: Object, default: null },
    publicPlans: { type: Array, default: () => [] },
});

const page = usePage();
const planModalOpen = ref(false);
const cancelModalOpen = ref(false);

const planForm = useForm({ plan_id: props.summary.plan.id ?? '' });
const cancelForm = useForm({ confirm: false });

const money = (cents) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format((cents ?? 0) / 100);

const planOptions = computed(() => props.publicPlans.map((plan) => ({
    value: plan.id,
    label: `${plan.name} — ${money(plan.price_cents)}/${plan.billing_interval === 'year' ? 'ano' : 'mês'}`,
})));

const selectedPlan = computed(() => props.publicPlans.find((plan) => plan.id === Number(planForm.plan_id)));

function submitPlanChange() {
    planForm.post('/organizations/billing/change-plan', {
        preserveScroll: true,
        onSuccess: () => {
            planModalOpen.value = false;
        },
    });
}

function submitCancel() {
    cancelForm.post('/organizations/billing/cancel', {
        preserveScroll: true,
        onSuccess: () => {
            cancelModalOpen.value = false;
            cancelForm.reset();
        },
    });
}
</script>

<template>
    <Head title="Billing" />
    <AppLayout title="Billing e assinatura" active-nav="organizations" :breadcrumbs="[{ label: 'Organizações', href: '/organizations' }, { label: 'Billing' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <Card :title="summary.plan.name">
                <p class="text-sm text-slate-600">{{ summary.plan.description }}</p>
                <p class="mt-2 text-2xl font-semibold">{{ money(summary.plan.price_cents) }}<span class="text-sm font-normal text-slate-500"> / mês</span></p>
                <div v-if="subscription" class="mt-3 flex flex-wrap gap-2 text-sm">
                    <Badge tone="neutral">Status: {{ subscription.status }}</Badge>
                    <span v-if="subscription.cancel_at_period_end" class="rounded-lg bg-amber-50 px-2 py-1 text-amber-900">Cancelamento agendado</span>
                    <span v-if="subscription.current_period_end" class="text-slate-500">Período até {{ subscription.current_period_end }}</span>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <Button size="sm" @click="planModalOpen = true">Alterar plano</Button>
                    <Button v-if="subscription && !subscription.cancel_at_period_end" size="sm" variant="secondary" @click="cancelModalOpen = true">Cancelar assinatura</Button>
                </div>
            </Card>

            <div class="grid gap-4 lg:grid-cols-2">
                <Card title="Próxima fatura">
                    <div v-if="nextOpenInvoice" class="text-sm">
                        <p class="text-2xl font-semibold">{{ money(nextOpenInvoice.amount_cents) }}</p>
                        <p class="mt-1 text-slate-500">Vencimento: {{ nextOpenInvoice.due_at }}</p>
                    </div>
                    <p v-else class="text-sm text-slate-500">Nenhuma fatura em aberto.</p>
                </Card>
                <Card title="Última fatura paga">
                    <div v-if="lastPaidInvoice" class="text-sm">
                        <p class="text-2xl font-semibold">{{ money(lastPaidInvoice.amount_cents) }}</p>
                        <p class="mt-1 text-slate-500">Paga em {{ lastPaidInvoice.paid_at }}</p>
                    </div>
                    <p v-else class="text-sm text-slate-500">Nenhum pagamento registrado ainda.</p>
                </Card>
            </div>

            <Card title="Histórico de faturas">
                <div v-if="invoices.length" class="divide-y divide-slate-200">
                    <div v-for="invoice in invoices" :key="invoice.id" class="flex items-center justify-between gap-3 py-3 text-sm">
                        <div>
                            <p class="font-medium">{{ money(invoice.amount_cents) }}</p>
                            <p class="text-slate-500">{{ invoice.period_start }} — {{ invoice.period_end }}</p>
                        </div>
                        <div class="text-right">
                            <Badge :tone="invoice.status === 'paid' ? 'success' : invoice.status === 'open' ? 'warning' : 'neutral'">{{ invoice.status }}</Badge>
                            <p class="mt-1 text-xs text-slate-500">{{ invoice.status === 'paid' ? invoice.paid_at : invoice.due_at }}</p>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-slate-500">Sem faturas registradas.</p>
            </Card>

            <Link href="/organizations/plan" class="text-sm font-semibold text-blue-700 hover:text-blue-900">Ver limites e uso do plano</Link>
        </div>

        <Modal v-if="planModalOpen" open title="Alterar plano" @close="planModalOpen = false">
            <form class="grid gap-3" @submit.prevent="submitPlanChange">
                <SelectInput id="billing-plan" v-model="planForm.plan_id" label="Novo plano" :options="planOptions" :error="planForm.errors.plan_id" />
                <div v-if="selectedPlan" class="rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                    <p class="font-medium">{{ selectedPlan.name }}</p>
                    <p class="mt-1">{{ selectedPlan.description }}</p>
                    <p class="mt-2">Upgrades são imediatos. Downgrades entram em vigor no fim do período atual.</p>
                </div>
            </form>
            <template #footer>
                <Button variant="secondary" @click="planModalOpen = false">Fechar</Button>
                <Button :disabled="planForm.processing" @click="submitPlanChange">Confirmar</Button>
            </template>
        </Modal>

        <Modal v-if="cancelModalOpen" open title="Cancelar assinatura" @close="cancelModalOpen = false">
            <p class="text-sm text-slate-600">O cancelamento será aplicado ao fim do período atual. Você continuará com acesso até lá.</p>
            <label class="mt-4 flex items-center gap-2 text-sm">
                <input v-model="cancelForm.confirm" type="checkbox" class="rounded border-slate-300" />
                Confirmo o cancelamento
            </label>
            <template #footer>
                <Button variant="secondary" @click="cancelModalOpen = false">Voltar</Button>
                <Button variant="danger" :disabled="!cancelForm.confirm || cancelForm.processing" @click="submitCancel">Cancelar assinatura</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
