<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Card from '../../Components/UI/Card.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';
import Button from '../../Components/UI/Button.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import DisplayDate from '../../Components/UI/DisplayDate.vue';

const props = defineProps({
    metrics: { type: Object, default: () => ({}) },
    value: { type: Object, default: () => ({}) },
    contracts_revenue: { type: Object, default: null },
    commercial: { type: Object, default: null },
    alerts: { type: Array, default: () => [] },
    structuralPendencies: { type: Array, default: () => [] },
    can_access_finance: { type: Boolean, default: false },
    can_access_crm: { type: Boolean, default: false },
    period: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();

const filterForm = useForm({
    period: props.filters.period ?? 'month',
    start_date: props.filters.start_date ?? '',
    end_date: props.filters.end_date ?? '',
});

const periodOptions = [
    { value: 'month', label: 'Mês atual' },
    { value: 'week', label: 'Últimos 7 dias' },
    { value: 'custom', label: 'Período customizado' },
];

const alertTones = {
    danger: 'danger',
    warning: 'warning',
    info: 'info',
};

const money = (cents) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format((cents ?? 0) / 100);

const formatDelta = (delta, { money: asMoney = false } = {}) => {
    if (delta === null || delta === undefined) {
        return null;
    }

    const prefix = delta > 0 ? '+' : '';
    if (asMoney) {
        return `${prefix}${money(delta)}`;
    }

    return `${prefix}${delta}`;
};

const formatDeltaPercent = (percent) => {
    if (percent === null || percent === undefined) {
        return 'sem base anterior';
    }

    const prefix = percent > 0 ? '+' : '';
    return `${prefix}${percent}% vs período anterior`;
};

const heroCards = computed(() => {
    if (props.value?.mode === 'finance') {
        return [
            {
                key: 'received',
                label: 'Recebido no período',
                value: money(props.value.received_cents),
                href: '/finance',
                delta: formatDelta(props.value.received_delta_cents, { money: true }),
                deltaHint: formatDeltaPercent(props.value.received_delta_percent),
                tone: (props.value.received_delta_cents ?? 0) >= 0 ? 'positive' : 'danger',
            },
            {
                key: 'open',
                label: 'Em aberto',
                value: money(props.value.open_receivables_cents),
                href: '/finance?status=open',
            },
            {
                key: 'overdue',
                label: 'Vencido',
                value: money(props.value.overdue_receivables_cents),
                href: '/finance?status=open',
                tone: (props.value.overdue_receivables_cents ?? 0) > 0 ? 'danger' : null,
            },
            {
                key: 'net',
                label: 'Saldo líquido do período',
                value: money(props.value.net_period_cents),
                href: '/finance',
                subtitle: 'Recebido − despesas pagas',
                tone: (props.value.net_period_cents ?? 0) >= 0 ? 'positive' : 'danger',
            },
        ];
    }

    return [
        {
            key: 'completed_tasks',
            label: 'Tarefas concluídas',
            value: props.value?.completed_tasks ?? 0,
            href: '/tasks',
            delta: formatDelta(props.value?.completed_tasks_delta),
            subtitle: 'No período selecionado',
            tone: 'positive',
        },
        {
            key: 'approved_documents',
            label: 'Docs aprovados',
            value: props.value?.approved_documents ?? 0,
            href: '/document-requests',
            delta: formatDelta(props.value?.approved_documents_delta),
            subtitle: 'No período selecionado',
        },
        {
            key: 'active_clients',
            label: 'Clientes ativos',
            value: props.value?.active_clients ?? props.metrics.active_clients ?? 0,
            href: '/clients?status=active',
        },
    ];
});

const secondaryCards = computed(() => {
    const cards = [];

    if (props.contracts_revenue) {
        cards.push(
            {
                key: 'mrr',
                label: 'MRR estimado',
                value: money(props.contracts_revenue.mrr_cents),
                href: '/contracts',
                subtitle: `${props.contracts_revenue.active_contracts ?? 0} contrato(s) ativo(s)`,
                tone: 'positive',
            },
            {
                key: 'at_risk',
                label: 'Valor em risco (30d)',
                value: money(props.contracts_revenue.at_risk_cents),
                href: props.contracts_revenue.href || '/contracts?expiring_soon=1',
                subtitle: `${props.contracts_revenue.expiring_count ?? 0} contrato(s) a vencer`,
                tone: (props.contracts_revenue.at_risk_cents ?? 0) > 0 ? 'warning' : null,
            },
        );
    }

    if (props.commercial && props.can_access_crm) {
        cards.push(
            {
                key: 'pipeline',
                label: 'Pipeline aberto',
                value: money(props.commercial.pipeline_cents),
                href: props.commercial.href || '/leads',
                subtitle: `${props.commercial.open_leads ?? 0} lead(s) em andamento`,
            },
            {
                key: 'gained',
                label: 'Ganho no período',
                value: money(props.commercial.gained_cents),
                href: props.commercial.href || '/leads',
                subtitle: 'Leads convertidos + propostas aceitas',
                tone: 'positive',
            },
        );
    }

    return cards;
});

const operationCards = computed(() => {
    const cards = [
        { key: 'open_tasks', label: 'Tarefas abertas', value: props.metrics.open_tasks ?? 0, href: '/tasks' },
        { key: 'overdue_tasks', label: 'Tarefas atrasadas', value: props.metrics.overdue_tasks ?? 0, href: '/tasks?flag=overdue', tone: props.metrics.overdue_tasks > 0 ? 'danger' : null },
        { key: 'completed_tasks', label: 'Tarefas concluídas', value: props.metrics.completed_tasks ?? 0, href: '/tasks', subtitle: 'No período' },
        { key: 'pending_documents', label: 'Docs pendentes', value: props.metrics.pending_documents ?? 0, href: '/document-requests' },
        { key: 'overdue_documents', label: 'Docs vencidos', value: props.metrics.overdue_documents ?? 0, href: '/document-requests?overdue=1', tone: props.metrics.overdue_documents > 0 ? 'danger' : null },
        { key: 'due_soon_documents', label: 'Docs a vencer (7d)', value: props.metrics.due_soon_documents ?? 0, href: '/document-requests', tone: props.metrics.due_soon_documents > 0 ? 'warning' : null },
        { key: 'open_tickets', label: 'Chamados abertos', value: props.metrics.open_tickets ?? 0, href: '/reports' },
        { key: 'active_clients', label: 'Clientes ativos', value: props.metrics.active_clients ?? 0, href: '/clients?status=active' },
        { key: 'expiring_contracts', label: 'Contratos a vencer (30d)', value: props.metrics.expiring_contracts ?? 0, href: '/contracts?expiring_soon=1', tone: (props.metrics.expiring_contracts ?? 0) > 0 ? 'warning' : null },
    ];

    if (props.can_access_finance) {
        cards.push(
            { key: 'open_receivables', label: 'Cobranças em aberto', value: money(props.metrics.open_receivables_cents), href: '/finance?status=open', isMoney: true },
            { key: 'overdue_receivables', label: 'Cobranças vencidas', value: money(props.metrics.overdue_receivables_cents), href: '/finance?status=open', tone: (props.metrics.overdue_receivables_cents ?? 0) > 0 ? 'danger' : null, isMoney: true },
            { key: 'received', label: 'Recebido no período', value: money(props.metrics.received_cents), href: '/finance', isMoney: true },
        );
    }

    return cards;
});

const hasValueData = computed(() => {
    if (props.value?.mode === 'finance') {
        return (props.value.received_cents ?? 0) > 0
            || (props.value.open_receivables_cents ?? 0) > 0
            || (props.value.overdue_receivables_cents ?? 0) > 0
            || (props.contracts_revenue?.mrr_cents ?? 0) > 0
            || (props.contracts_revenue?.at_risk_cents ?? 0) > 0
            || (props.commercial?.pipeline_cents ?? 0) > 0
            || (props.commercial?.gained_cents ?? 0) > 0;
    }

    return (props.value?.completed_tasks ?? 0) > 0
        || (props.value?.approved_documents ?? 0) > 0
        || (props.value?.active_clients ?? 0) > 0
        || (props.contracts_revenue?.mrr_cents ?? 0) > 0
        || (props.commercial?.pipeline_cents ?? 0) > 0;
});

const hasOperationalData = computed(() => {
    const numericKeys = ['open_tasks', 'overdue_tasks', 'completed_tasks', 'pending_documents', 'overdue_documents', 'due_soon_documents', 'open_tickets', 'active_clients', 'expiring_contracts'];
    const hasMetrics = numericKeys.some((key) => (props.metrics[key] ?? 0) > 0);
    const hasFinance = props.can_access_finance && (
        (props.metrics.open_receivables_cents ?? 0) > 0
        || (props.metrics.overdue_receivables_cents ?? 0) > 0
        || (props.metrics.received_cents ?? 0) > 0
    );

    return hasValueData.value || hasMetrics || hasFinance || props.alerts.length > 0 || props.structuralPendencies.length > 0;
});

function applyFilters() {
    router.get('/dashboard', filterForm.data(), { preserveState: true, preserveScroll: true });
}

function valueClass(tone) {
    if (tone === 'danger') {
        return 'text-red-700';
    }

    if (tone === 'warning') {
        return 'text-amber-700';
    }

    if (tone === 'positive') {
        return 'text-emerald-700';
    }

    return 'text-slate-950';
}

function deltaClass(tone) {
    if (tone === 'danger') {
        return 'text-red-600';
    }

    if (tone === 'positive') {
        return 'text-emerald-600';
    }

    return 'text-slate-500';
}
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout title="Dashboard" active-nav="dashboard" :breadcrumbs="[{ label: 'Dashboard' }]">
        <Alert v-if="page.props.flash?.status" tone="success" class="mb-6">{{ page.props.flash.status }}</Alert>

        <div class="grid gap-6">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <form class="grid gap-3 lg:grid-cols-[220px_1fr_1fr_auto]" @submit.prevent="applyFilters">
                    <SelectInput id="dashboard-period" v-model="filterForm.period" label="Período" :options="periodOptions" />
                    <TextInput
                        v-if="filterForm.period === 'custom'"
                        id="dashboard-start"
                        v-model="filterForm.start_date"
                        type="date"
                        label="Início"
                    />
                    <TextInput
                        v-if="filterForm.period === 'custom'"
                        id="dashboard-end"
                        v-model="filterForm.end_date"
                        type="date"
                        label="Fim"
                    />
                    <div class="flex items-end gap-2" :class="filterForm.period === 'custom' ? '' : 'lg:col-start-4'">
                        <Button type="submit" variant="secondary">Aplicar</Button>
                    </div>
                </form>
                <p v-if="period.start && period.end" class="mt-3 text-xs text-slate-500">
                    Exibindo resultado de <DisplayDate :value="period.start" /> até <DisplayDate :value="period.end" />.
                </p>
            </div>

            <div v-if="!hasOperationalData" class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-8 text-center">
                <p class="text-sm font-medium text-slate-700">Ainda sem resultado para mostrar</p>
                <p class="mt-1 text-sm text-slate-500">
                    Registre a primeira cobrança, contrato ou lead para ver o valor que o escritório está gerando.
                </p>
                <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                    <Link
                        v-if="can_access_finance"
                        href="/finance"
                        class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800"
                    >
                        Registrar cobrança
                    </Link>
                    <Link
                        href="/contracts"
                        class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:border-slate-400"
                    >
                        Criar contrato
                    </Link>
                    <Link
                        v-if="can_access_crm"
                        href="/leads"
                        class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:border-slate-400"
                    >
                        Abrir lead
                    </Link>
                </div>
            </div>

            <section v-if="hasOperationalData" class="grid gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Resultado do período</h2>
                    <p class="text-xs text-slate-500">O que entrou e o que está em jogo — não só o que está atrasado.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Link
                        v-for="card in heroCards"
                        :key="card.key"
                        :href="card.href"
                        class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow"
                    >
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ card.label }}</p>
                        <p class="mt-2 text-3xl font-semibold tracking-tight" :class="valueClass(card.tone)">{{ card.value }}</p>
                        <p v-if="card.delta" class="mt-2 text-xs font-medium" :class="deltaClass(card.tone)">
                            {{ card.delta }}
                            <span v-if="card.deltaHint" class="font-normal text-slate-500"> · {{ card.deltaHint }}</span>
                        </p>
                        <p v-else-if="card.subtitle" class="mt-2 text-xs text-slate-500">{{ card.subtitle }}</p>
                    </Link>
                </div>
            </section>

            <section v-if="secondaryCards.length" class="grid gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Receita recorrente e comercial</h2>
                    <p class="text-xs text-slate-500">Contratos ativos, risco de renovação e pipeline.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Link
                        v-for="card in secondaryCards"
                        :key="card.key"
                        :href="card.href"
                        class="block rounded-lg border border-slate-200 bg-white p-4 transition hover:border-blue-300 hover:shadow-sm"
                    >
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ card.label }}</p>
                        <p class="mt-2 text-2xl font-semibold" :class="valueClass(card.tone)">{{ card.value }}</p>
                        <p v-if="card.subtitle" class="mt-1 text-xs text-slate-500">{{ card.subtitle }}</p>
                    </Link>
                </div>
            </section>

            <Card v-if="alerts.length" title="Alertas operacionais" subtitle="Clique para ir direto à listagem filtrada.">
                <div class="grid gap-2">
                    <Link
                        v-for="alert in alerts"
                        :key="alert.type"
                        :href="alert.href"
                        class="block rounded-lg transition hover:opacity-90"
                    >
                        <Alert :tone="alertTones[alert.severity] ?? 'warning'">
                            <span class="flex items-center justify-between gap-3">
                                <span>{{ alert.label }}</span>
                                <span class="rounded-full bg-white/60 px-2 py-0.5 text-xs font-semibold">{{ alert.count }}</span>
                            </span>
                        </Alert>
                    </Link>
                </div>
            </Card>

            <section v-if="hasOperationalData" class="grid gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Operação</h2>
                    <p class="text-xs text-slate-500">Pendências e volume do dia a dia.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Link
                        v-for="card in operationCards"
                        :key="card.key"
                        :href="card.href"
                        class="block rounded-lg border border-slate-200 bg-slate-50/80 p-3 transition hover:border-blue-300 hover:bg-white"
                    >
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ card.label }}</p>
                        <p class="mt-1 text-xl font-semibold" :class="valueClass(card.tone)">{{ card.value }}</p>
                        <p v-if="card.subtitle" class="mt-1 text-xs text-slate-500">{{ card.subtitle }}</p>
                    </Link>
                </div>
            </section>

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

                <Card title="Resumo de clientes">
                    <dl class="grid gap-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Alto risco</dt>
                            <dd class="font-semibold text-slate-950">{{ metrics.high_risk_clients ?? 0 }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Inadimplentes</dt>
                            <dd class="font-semibold text-slate-950">{{ metrics.delinquent_clients ?? 0 }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Sem contato principal</dt>
                            <dd class="font-semibold text-slate-950">{{ metrics.without_primary_contact ?? 0 }}</dd>
                        </div>
                    </dl>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
