<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import ClientPortalLayout from '../../Layouts/ClientPortalLayout.vue';
import Badge from '../../Components/UI/Badge.vue';
import Button from '../../Components/UI/Button.vue';
import Card from '../../Components/UI/Card.vue';
import Modal from '../../Components/Overlays/Modal.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';

const props = defineProps({
    summary: { type: Object, default: () => ({ open_balance_cents: 0, overdue_count: 0, open_count: 0 }) },
    payment_instructions: { type: String, default: null },
    receivables: { type: Array, default: () => [] },
    highlightReceivableId: { type: Number, default: null },
});

const selectedReceivable = ref(null);
const detailOpen = ref(false);
const copied = ref(false);

const money = (cents) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format((cents ?? 0) / 100);

function openDetail(receivable) {
    selectedReceivable.value = receivable;
    detailOpen.value = true;
    copied.value = false;
}

async function copyPix(payload) {
    if (!payload || !navigator.clipboard) {
        return;
    }

    await navigator.clipboard.writeText(payload);
    copied.value = true;
}

function openPaymentUrl(url) {
    window.open(url, '_blank');
}

onMounted(() => {
    if (!props.highlightReceivableId) {
        return;
    }

    const receivable = props.receivables.find((item) => item.id === props.highlightReceivableId);
    if (receivable) {
        openDetail(receivable);
    }
});

const sortedReceivables = computed(() => [...props.receivables].sort((left, right) => {
    if (left.is_overdue !== right.is_overdue) {
        return left.is_overdue ? -1 : 1;
    }

    return (left.due_at_raw ?? '').localeCompare(right.due_at_raw ?? '');
}));
</script>

<template>
    <Head title="Cobranças · Portal" />
    <ClientPortalLayout title="Cobranças" active-nav="more">
        <div class="grid gap-4">
            <p class="text-sm text-slate-600">Pague com Pix ou boleto quando o escritório gerar a cobrança. Sem link, use as instruções abaixo.</p>

            <div class="grid gap-3 sm:grid-cols-3">
                <Card title="Total em aberto"><p class="text-2xl font-semibold text-slate-950">{{ money(summary.open_balance_cents) }}</p></Card>
                <Card title="Cobranças abertas"><p class="text-2xl font-semibold text-slate-950">{{ summary.open_count ?? 0 }}</p></Card>
                <Card title="Vencidas"><p class="text-2xl font-semibold text-red-700">{{ summary.overdue_count ?? 0 }}</p></Card>
            </div>

            <Card v-if="payment_instructions" title="Instruções de pagamento">
                <p class="whitespace-pre-wrap text-sm leading-6 text-slate-700">{{ payment_instructions }}</p>
            </Card>

            <div v-if="sortedReceivables.length" class="grid gap-3">
                <article
                    v-for="receivable in sortedReceivables"
                    :key="receivable.id"
                    class="rounded-2xl border bg-white p-5 shadow-sm"
                    :class="receivable.is_overdue ? 'border-red-200 bg-red-50/40' : 'border-slate-200'"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-base font-semibold text-slate-950">{{ receivable.description }}</h2>
                                <Badge v-if="receivable.is_overdue" tone="danger">Vencida</Badge>
                            </div>
                            <p class="mt-1 text-sm text-slate-500">Vencimento: {{ receivable.due_at || 'Sem prazo' }}</p>
                        </div>
                        <StatusPill :status="receivable.status" />
                    </div>
                    <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="text-2xl font-semibold" :class="receivable.is_overdue ? 'text-red-700' : 'text-slate-950'">{{ money(receivable.balance_cents) }}</p>
                            <p class="mt-1 text-sm text-slate-500">Total: {{ money(receivable.amount_cents) }} · Pago: {{ money(receivable.paid_amount_cents) }}</p>
                        </div>
                        <Button v-if="receivable.can_pay" size="sm" @click="openDetail(receivable)">Pagar agora</Button>
                        <Button v-else size="sm" variant="secondary" @click="openDetail(receivable)">Ver cobrança</Button>
                    </div>
                </article>
            </div>

            <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                <p class="text-base font-medium text-slate-900">Nenhuma cobrança disponível</p>
            </div>
        </div>

        <Modal v-if="detailOpen && selectedReceivable" open :title="selectedReceivable.description" @close="detailOpen = false">
            <dl class="grid gap-3 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Status</dt><dd><StatusPill :status="selectedReceivable.status" /></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Vencimento</dt><dd class="font-medium text-slate-950">{{ selectedReceivable.due_at || 'Sem prazo' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Saldo em aberto</dt><dd class="font-semibold text-slate-950">{{ money(selectedReceivable.balance_cents) }}</dd></div>
                <div v-if="selectedReceivable.payment_reference" class="flex justify-between gap-3"><dt class="text-slate-500">Referência</dt><dd class="font-medium text-slate-950">{{ selectedReceivable.payment_reference }}</dd></div>
                <div v-if="selectedReceivable.notes" class="grid gap-1"><dt class="text-slate-500">Observações</dt><dd class="text-slate-700">{{ selectedReceivable.notes }}</dd></div>
                <div v-if="selectedReceivable.charge && selectedReceivable.can_pay" class="grid gap-3 rounded-lg border border-violet-100 bg-violet-50/60 p-3">
                    <p class="font-medium text-slate-800">Pagar agora</p>
                    <img
                        v-if="selectedReceivable.charge.pix_encoded_image"
                        :src="`data:image/png;base64,${selectedReceivable.charge.pix_encoded_image}`"
                        alt="QR Code Pix"
                        class="mx-auto h-44 w-44 rounded-lg border border-slate-200 bg-white p-2"
                    />
                    <p v-if="selectedReceivable.charge.pix_payload" class="break-all font-mono text-xs text-slate-700">{{ selectedReceivable.charge.pix_payload }}</p>
                    <p v-if="selectedReceivable.charge.identification_field" class="text-sm text-slate-700">Boleto: {{ selectedReceivable.charge.identification_field }}</p>
                </div>
                <div v-if="payment_instructions" class="grid gap-1 rounded-lg bg-slate-50 p-3"><dt class="font-medium text-slate-700">Como pagar</dt><dd class="whitespace-pre-wrap text-slate-700">{{ payment_instructions }}</dd></div>
            </dl>
            <template #footer>
                <Button v-if="selectedReceivable.charge?.pix_payload && selectedReceivable.can_pay" @click="copyPix(selectedReceivable.charge.pix_payload)">
                    {{ copied ? 'Código copiado' : 'Copiar código Pix' }}
                </Button>
                <Button
                    v-if="selectedReceivable.charge?.bank_slip_url || selectedReceivable.payment_url"
                    variant="secondary"
                    @click="openPaymentUrl(selectedReceivable.charge?.bank_slip_url || selectedReceivable.payment_url)"
                >
                    {{ selectedReceivable.charge?.bank_slip_url ? 'Abrir boleto' : 'Abrir link de pagamento' }}
                </Button>
                <Button variant="secondary" @click="detailOpen = false">Fechar</Button>
            </template>
        </Modal>
    </ClientPortalLayout>
</template>
