<script setup>
import { Head } from '@inertiajs/vue3';
import ClientPortalLayout from '../../Layouts/ClientPortalLayout.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';

defineProps({
    receivables: { type: Array, default: () => [] },
});

const money = (cents) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format((cents ?? 0) / 100);
</script>

<template>
    <Head title="Cobranças · Portal" />
    <ClientPortalLayout title="Cobranças" active-nav="more">
        <div class="grid gap-4">
            <p class="text-sm text-slate-600">Consulte cobranças liberadas pelo escritório para visualização no portal.</p>

            <div v-if="receivables.length" class="grid gap-3">
                <article v-for="receivable in receivables" :key="receivable.id" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">{{ receivable.description }}</h2>
                            <p class="mt-1 text-sm text-slate-500">Vencimento: {{ receivable.due_at || 'Sem prazo' }}</p>
                        </div>
                        <StatusPill :status="receivable.status" />
                    </div>
                    <div class="mt-4 flex flex-wrap items-end justify-between gap-2">
                        <p class="text-2xl font-semibold text-slate-950">{{ money(receivable.balance_cents) }}</p>
                        <p class="text-sm text-slate-500">Total: {{ money(receivable.amount_cents) }}</p>
                    </div>
                </article>
            </div>

            <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                <p class="text-base font-medium text-slate-900">Nenhuma cobrança disponível</p>
            </div>
        </div>
    </ClientPortalLayout>
</template>
