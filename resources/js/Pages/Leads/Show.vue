<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Button from '../../Components/UI/Button.vue';
import Badge from '../../Components/UI/Badge.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import CurrencyInput from '../../Components/Forms/CurrencyInput.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';
import TextareaInput from '../../Components/Forms/TextareaInput.vue';
import { formatBrlCurrency } from '../../lib/money';

const props = defineProps({
    lead: { type: Object, required: true },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const startOnboarding = ref(false);

const stageForm = useForm({
    stage: props.lead.stage,
    lost_reason: props.lead.lost_reason || '',
});

const activityForm = useForm({
    type: 'note',
    body: '',
    happened_at: '',
});

const proposalForm = useForm({
    title: '',
    amount_cents: '',
    status: 'draft',
    notes: '',
});

const convertForm = useForm({
    start_onboarding: false,
});

function submitStage() {
    stageForm.patch(`/leads/${props.lead.id}/stage`, { preserveScroll: true });
}

function submitActivity() {
    activityForm.post(`/leads/${props.lead.id}/activities`, {
        preserveScroll: true,
        onSuccess: () => activityForm.reset('body', 'happened_at'),
    });
}

function submitProposal() {
    proposalForm.post(`/leads/${props.lead.id}/proposals`, {
        preserveScroll: true,
        onSuccess: () => proposalForm.reset(),
    });
}

function updateProposalStatus(proposalId, status) {
    useForm({ status }).patch(`/leads/${props.lead.id}/proposals/${proposalId}/status`, { preserveScroll: true });
}

function convertLead() {
    convertForm.start_onboarding = startOnboarding.value;
    convertForm.post(`/leads/${props.lead.id}/convert`);
}

const money = formatBrlCurrency;
</script>

<template>
    <Head :title="`Lead — ${lead.name}`" />
    <AppLayout title="Lead" active-nav="leads" :breadcrumbs="[{ label: 'CRM', href: '/leads' }, { label: lead.name }]">
        <div class="grid gap-4 lg:grid-cols-[1.2fr_1fr]">
            <div class="grid gap-4">
                <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
                <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

                <section class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">{{ lead.name }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ lead.email || 'Sem e-mail' }} · {{ lead.phone || 'Sem telefone' }}</p>
                            <p class="mt-2 text-xs text-slate-500">Origem: {{ lead.origin_label || '—' }} · Interesse: {{ lead.service_interest || '—' }}</p>
                        </div>
                        <Badge tone="secondary">{{ lead.stage_label }}</Badge>
                    </div>
                    <p class="mt-3 text-sm font-medium text-slate-800">{{ money(lead.estimated_value_cents) }}</p>
                    <p v-if="lead.client" class="mt-2 text-sm">
                        Cliente:
                        <Link :href="`/clients/${lead.client.id}?tab=commercial`" class="font-semibold text-slate-900 underline">{{ lead.client.display_name }}</Link>
                    </p>
                </section>

                <section v-if="can.manage && !lead.is_converted" class="rounded-xl border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-semibold text-slate-900">Mover no funil</h3>
                    <form class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="submitStage">
                        <SelectInput v-model="stageForm.stage" label="Etapa" :options="options.stages" :error="stageForm.errors.stage" />
                        <TextInput v-if="stageForm.stage === 'lost'" v-model="stageForm.lost_reason" label="Motivo da perda" :error="stageForm.errors.lost_reason" />
                        <div class="sm:col-span-2 flex justify-end">
                            <Button type="submit" size="sm" :disabled="stageForm.processing">Atualizar etapa</Button>
                        </div>
                    </form>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-semibold text-slate-900">Atividades</h3>
                    <form v-if="can.manage" class="mt-3 grid gap-3" @submit.prevent="submitActivity">
                        <SelectInput v-model="activityForm.type" label="Tipo" :options="options.activity_types" :error="activityForm.errors.type" />
                        <TextareaInput v-model="activityForm.body" label="Descrição" :error="activityForm.errors.body" />
                        <div class="flex justify-end">
                            <Button type="submit" size="sm" :disabled="activityForm.processing">Registrar</Button>
                        </div>
                    </form>
                    <ul class="mt-4 grid gap-2">
                        <li v-for="activity in lead.activities" :key="activity.id" class="rounded-lg border border-slate-100 px-3 py-2">
                            <p class="text-xs font-semibold text-slate-700">{{ activity.type_label }} · {{ activity.happened_at }}</p>
                            <p class="mt-1 text-sm text-slate-800">{{ activity.body }}</p>
                        </li>
                        <li v-if="!lead.activities.length" class="text-xs text-slate-400">Nenhuma atividade ainda.</li>
                    </ul>
                </section>
            </div>

            <div class="grid gap-4 content-start">
                <section v-if="can.manage && !lead.is_converted" class="rounded-xl border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-semibold text-slate-900">Converter em cliente</h3>
                    <label class="mt-3 flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="startOnboarding" type="checkbox" class="rounded border-slate-300" />
                        Iniciar onboarding após converter
                    </label>
                    <Button class="mt-3" size="sm" :disabled="convertForm.processing" @click="convertLead">Converter</Button>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-semibold text-slate-900">Propostas</h3>
                    <form v-if="can.manage" class="mt-3 grid gap-3" @submit.prevent="submitProposal">
                        <TextInput v-model="proposalForm.title" label="Título" :error="proposalForm.errors.title" />
                        <CurrencyInput id="proposal-amount" v-model="proposalForm.amount_cents" label="Valor" :error="proposalForm.errors.amount_cents" />
                        <SelectInput v-model="proposalForm.status" label="Status" :options="options.proposal_statuses" :error="proposalForm.errors.status" />
                        <TextareaInput v-model="proposalForm.notes" label="Notas" :error="proposalForm.errors.notes" />
                        <div class="flex justify-end">
                            <Button type="submit" size="sm" :disabled="proposalForm.processing">Criar proposta</Button>
                        </div>
                    </form>
                    <ul class="mt-4 grid gap-2">
                        <li v-for="proposal in lead.proposals" :key="proposal.id" class="rounded-lg border border-slate-100 px-3 py-2">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900">{{ proposal.title }}</p>
                                <Badge tone="secondary">{{ proposal.status_label }}</Badge>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">{{ money(proposal.amount_cents) }}</p>
                            <div v-if="can.manage" class="mt-2 flex flex-wrap gap-2">
                                <Button size="sm" variant="secondary" @click="updateProposalStatus(proposal.id, 'sent')">Enviada</Button>
                                <Button size="sm" variant="secondary" @click="updateProposalStatus(proposal.id, 'accepted')">Aceita</Button>
                                <Button size="sm" variant="secondary" @click="updateProposalStatus(proposal.id, 'rejected')">Recusada</Button>
                            </div>
                        </li>
                        <li v-if="!lead.proposals.length" class="text-xs text-slate-400">Nenhuma proposta.</li>
                    </ul>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
