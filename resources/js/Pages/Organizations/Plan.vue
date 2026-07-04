<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Card from '../../Components/UI/Card.vue';
import Badge from '../../Components/UI/Badge.vue';

defineProps({
    summary: { type: Object, required: true },
});

const page = usePage();
const money = (cents) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format((cents ?? 0) / 100);
</script>

<template>
    <Head title="Plano e uso" />
    <AppLayout title="Plano e uso" active-nav="organizations" :breadcrumbs="[{ label: 'Organizações', href: '/organizations' }, { label: 'Plano' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <Card :title="summary.plan.name">
                <p class="text-sm text-slate-600">{{ summary.plan.description }}</p>
                <p class="mt-3 text-2xl font-semibold text-slate-950">{{ money(summary.plan.price_cents) }}<span class="text-sm font-normal text-slate-500"> / {{ summary.plan.billing_interval === 'year' ? 'ano' : 'mês' }}</span></p>
            </Card>

            <Card v-if="summary.subscription" title="Assinatura">
                <dl class="grid gap-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Status</dt>
                        <dd class="font-medium capitalize">{{ summary.subscription.status.replace('_', ' ') }}</dd>
                    </div>
                    <div v-if="summary.subscription.trial_days_left !== null" class="flex justify-between gap-3">
                        <dt class="text-slate-500">Trial restante</dt>
                        <dd class="font-medium">{{ summary.subscription.trial_days_left }} {{ summary.subscription.trial_days_left === 1 ? 'dia' : 'dias' }}</dd>
                    </div>
                    <div v-if="summary.subscription.current_period_end" class="flex justify-between gap-3">
                        <dt class="text-slate-500">Próximo vencimento</dt>
                        <dd class="font-medium">{{ new Date(summary.subscription.current_period_end).toLocaleDateString('pt-BR') }}</dd>
                    </div>
                    <div v-if="summary.subscription.on_grace_period" class="rounded-lg bg-amber-50 px-3 py-2 text-amber-900">
                        Assinatura inadimplente — você está no período de tolerância.
                    </div>
                </dl>
            </Card>

            <Card title="Limites e consumo">
                <div class="grid gap-4">
                    <div v-for="item in summary.limits" :key="item.key" class="grid gap-2">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium text-slate-900">{{ item.label }}</span>
                            <span class="text-slate-600">
                                {{ item.current }} / {{ item.unlimited ? 'Ilimitado' : item.limit }} {{ item.unit }}
                            </span>
                        </div>
                        <div v-if="!item.unlimited" class="h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-blue-600 transition-all" :class="item.percentage >= 80 ? 'bg-amber-500' : ''" :style="{ width: `${item.percentage}%` }" />
                        </div>
                    </div>
                </div>
            </Card>

            <Card title="Recursos incluídos">
                <ul class="grid gap-2 sm:grid-cols-2">
                    <li v-for="feature in summary.features" :key="feature.key" class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <span>{{ feature.label }}</span>
                        <Badge :tone="feature.enabled ? 'success' : 'neutral'">{{ feature.enabled ? 'Incluído' : 'Não incluído' }}</Badge>
                    </li>
                </ul>
            </Card>

            <p class="text-sm text-slate-500">Para alterar de plano ou ampliar limites, entre em contato com o suporte Docflow.</p>
            <Link href="/organizations" class="text-sm font-semibold text-blue-700 hover:text-blue-900">Voltar para organizações</Link>
        </div>
    </AppLayout>
</template>
