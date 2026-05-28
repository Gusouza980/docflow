<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Card from '../../Components/UI/Card.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';

defineProps({
    metrics: { type: Object, default: () => ({}) },
    alerts: { type: Array, default: () => [] },
    structuralPendencies: { type: Array, default: () => [] },
});

const page = usePage();
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout title="Dashboard" active-nav="dashboard" :breadcrumbs="[{ label: 'Dashboard' }]">
        <Alert v-if="page.props.flash?.status" tone="success" class="mb-6">{{ page.props.flash.status }}</Alert>
        <div class="grid gap-4">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Card title="Ativos">
                    <p class="text-3xl font-semibold text-slate-950">{{ metrics.active ?? 0 }}</p>
                </Card>
                <Card title="Negociação">
                    <p class="text-3xl font-semibold text-slate-950">{{ metrics.negotiation ?? 0 }}</p>
                </Card>
                <Card title="Alto risco">
                    <p class="text-3xl font-semibold text-slate-950">{{ metrics.high_risk ?? 0 }}</p>
                </Card>
                <Card title="Sem contato principal">
                    <p class="text-3xl font-semibold text-slate-950">{{ metrics.without_primary_contact ?? 0 }}</p>
                </Card>
            </div>

            <div v-if="alerts.length" class="grid gap-2">
                <Alert v-for="alert in alerts" :key="alert.type" tone="warning">{{ alert.label }}</Alert>
            </div>

            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
                <Card title="Pendências estruturais" subtitle="Clientes sem contato principal para priorização operacional.">
                    <div v-if="structuralPendencies.length" class="divide-y divide-slate-100">
                        <div v-for="client in structuralPendencies" :key="client.id" class="flex items-center justify-between gap-4 py-3">
                            <div>
                                <Link :href="client.href" class="text-sm font-semibold text-slate-950 hover:text-blue-700">{{ client.display_name }}</Link>
                                <p class="mt-1 text-xs text-slate-500">{{ client.responsible || 'Sem responsável principal' }}</p>
                            </div>
                            <StatusPill :status="client.status" />
                        </div>
                    </div>
                    <p v-else class="text-sm text-slate-500">Nenhuma pendência estrutural encontrada.</p>
                </Card>
                <Card title="Resumo">
                    <dl class="grid gap-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Inativos</dt>
                            <dd class="font-semibold text-slate-950">{{ metrics.inactive ?? 0 }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Inadimplentes</dt>
                            <dd class="font-semibold text-slate-950">{{ metrics.delinquent ?? 0 }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Encerrados</dt>
                            <dd class="font-semibold text-slate-950">{{ metrics.closed ?? 0 }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Sem responsável</dt>
                            <dd class="font-semibold text-slate-950">{{ metrics.without_responsible ?? 0 }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Tarefas atrasadas</dt>
                            <dd class="font-semibold text-slate-950">{{ metrics.overdue_tasks ?? 0 }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Docs vencidos</dt>
                            <dd class="font-semibold text-slate-950">{{ metrics.overdue_documents ?? 0 }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Chamados abertos</dt>
                            <dd class="font-semibold text-slate-950">{{ metrics.open_tickets ?? 0 }}</dd>
                        </div>
                    </dl>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
