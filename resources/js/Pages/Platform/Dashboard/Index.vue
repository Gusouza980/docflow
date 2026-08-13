<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import PlatformLayout from '../../../Layouts/PlatformLayout.vue';
import Alert from '../../../Components/Feedback/Alert.vue';
import Card from '../../../Components/UI/Card.vue';
import Button from '../../../Components/UI/Button.vue';

defineProps({
    metrics: {
        type: Object,
        default: () => ({
            total_organizations: 0,
            active_organizations: 0,
            suspended_organizations: 0,
            mrr_cents: 0,
            past_due_organizations: 0,
            trials_expiring_soon: 0,
            overdue_invoices: 0,
        }),
    },
});

const page = usePage();
const money = (cents) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format((cents ?? 0) / 100);
</script>

<template>
    <Head title="Platform · Dashboard" />
    <PlatformLayout title="Dashboard da plataforma" active-nav="dashboard">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>

            <p class="text-sm text-slate-600">
                Visão global dos tenants cadastrados no Docflow. Use o menu Organizações para suspender, reativar ou registrar notas internas.
                Consulte o
                <Link href="/platform/guides" class="font-medium text-violet-700 hover:underline">guia de uso</Link>
                para fluxos, limites e portal do cliente.
            </p>

            <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
                <Card title="Total de organizações">
                    <p class="text-3xl font-semibold text-slate-950">{{ metrics.total_organizations }}</p>
                </Card>
                <Card title="Ativas">
                    <p class="text-3xl font-semibold text-emerald-700">{{ metrics.active_organizations }}</p>
                </Card>
                <Card title="Suspensas">
                    <p class="text-3xl font-semibold" :class="metrics.suspended_organizations > 0 ? 'text-red-700' : 'text-slate-950'">{{ metrics.suspended_organizations }}</p>
                </Card>
                <Card title="MRR estimado">
                    <p class="text-2xl font-semibold text-violet-700">{{ money(metrics.mrr_cents) }}</p>
                </Card>
                <Card title="Inadimplentes">
                    <p class="text-3xl font-semibold text-amber-700">{{ metrics.past_due_organizations }}</p>
                </Card>
                <Card title="Trials (7 dias)">
                    <p class="text-3xl font-semibold text-blue-700">{{ metrics.trials_expiring_soon }}</p>
                </Card>
            </div>

            <Card v-if="metrics.overdue_invoices > 0" title="Faturas vencidas">
                <p class="text-2xl font-semibold text-red-700">{{ metrics.overdue_invoices }}</p>
                <Link href="/platform/invoices?overdue=1" class="mt-2 inline-block text-sm font-semibold text-violet-700">Ver faturas vencidas</Link>
            </Card>

            <div class="flex flex-wrap gap-3">
                <Link href="/platform/organizations"><Button>Gerenciar organizações</Button></Link>
                <Link href="/platform/invoices"><Button variant="secondary">Faturas SaaS</Button></Link>
            </div>
        </div>
    </PlatformLayout>
</template>
