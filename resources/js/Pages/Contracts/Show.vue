<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Card from '../../Components/UI/Card.vue';
import Button from '../../Components/UI/Button.vue';
import Badge from '../../Components/UI/Badge.vue';
import TextInput from '../../Components/Forms/TextInput.vue';

const props = defineProps({
    contract: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();

const renewForm = useForm({
    ends_at: '',
});

const cancelForm = useForm({
    cancel_reason: '',
});

const money = (cents) => {
    if (cents === null || cents === undefined) {
        return '—';
    }

    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(cents) / 100);
};

function renew() {
    renewForm.post(`/contracts/${props.contract.id}/renew`, { preserveScroll: true });
}

function cancel() {
    if (!window.confirm('Cancelar este contrato?')) {
        return;
    }

    cancelForm.post(`/contracts/${props.contract.id}/cancel`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Contrato ${contract.code}`" />
    <AppLayout
        :title="`Contrato ${contract.code}`"
        active-nav="contracts"
        :breadcrumbs="[{ label: 'Contratos', href: '/contracts' }, { label: contract.code }]"
    >
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <Card :title="contract.code">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm text-slate-600">
                            Cliente:
                            <Link v-if="contract.client_href" :href="contract.client_href" class="font-semibold underline">{{ contract.client_name }}</Link>
                            <span v-else>{{ contract.client_name }}</span>
                        </p>
                        <p class="mt-2 text-sm text-slate-700">{{ money(contract.amount_cents) }} · {{ contract.billing_interval_label }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ contract.starts_at || '—' }} → {{ contract.ends_at || 'sem término' }}</p>
                    </div>
                    <Badge :tone="contract.is_expiring_soon ? 'warning' : 'secondary'">{{ contract.status_label }}</Badge>
                </div>
            </Card>

            <div class="grid gap-4 lg:grid-cols-2">
                <Card title="Limites do contrato">
                    <p class="text-xs font-semibold uppercase text-slate-500">O que está incluso</p>
                    <p class="mt-1 whitespace-pre-wrap text-sm text-slate-700">{{ contract.scope_included || '—' }}</p>
                    <p class="mt-4 text-xs font-semibold uppercase text-slate-500">O que não está incluso</p>
                    <p class="mt-1 whitespace-pre-wrap text-sm text-slate-700">{{ contract.scope_excluded || '—' }}</p>
                </Card>

                <Card title="Serviços vinculados">
                    <ul v-if="contract.services?.length" class="grid gap-2">
                        <li v-for="service in contract.services" :key="service.id" class="text-sm text-slate-700">
                            {{ service.name }} · {{ service.status_label }}
                        </li>
                    </ul>
                    <p v-else class="text-sm text-slate-500">Nenhum serviço vinculado.</p>
                </Card>
            </div>

            <Card v-if="can.manage && contract.status !== 'canceled'" title="Ações">
                <div class="grid gap-4 lg:grid-cols-2">
                    <form class="grid gap-3" @submit.prevent="renew">
                        <p class="text-sm font-semibold text-slate-900">Renovar</p>
                        <TextInput id="renew-ends-at" v-model="renewForm.ends_at" type="date" label="Nova data de término (opcional)" :error="renewForm.errors.ends_at" />
                        <Button type="submit" size="sm" :disabled="renewForm.processing">Renovar contrato</Button>
                    </form>
                    <form class="grid gap-3" @submit.prevent="cancel">
                        <p class="text-sm font-semibold text-slate-900">Cancelar</p>
                        <TextInput id="cancel-reason" v-model="cancelForm.cancel_reason" label="Motivo" :error="cancelForm.errors.cancel_reason" />
                        <Button type="submit" size="sm" variant="danger" :disabled="cancelForm.processing">Cancelar contrato</Button>
                    </form>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
