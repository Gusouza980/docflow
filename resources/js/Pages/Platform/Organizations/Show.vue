<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import PlatformLayout from '../../../Layouts/PlatformLayout.vue';
import Alert from '../../../Components/Feedback/Alert.vue';
import Card from '../../../Components/UI/Card.vue';
import Button from '../../../Components/UI/Button.vue';
import Modal from '../../../Components/Overlays/Modal.vue';
import DataTable from '../../../Components/Data/DataTable.vue';
import SelectInput from '../../../Components/Forms/SelectInput.vue';
import TextareaInput from '../../../Components/Forms/TextareaInput.vue';
import TextInput from '../../../Components/Forms/TextInput.vue';
import StatusPill from '../../../Components/UI/StatusPill.vue';

const props = defineProps({
    organization: { type: Object, required: true },
    planSummary: { type: Object, default: null },
    planOptions: { type: Array, default: () => [] },
    subscription: { type: Object, default: null },
    activeOverride: { type: Object, default: null },
    metrics: { type: Object, default: () => ({}) },
    members: { type: Array, default: () => [] },
    recentAuditLogs: { type: Array, default: () => [] },
});

const page = usePage();
const suspendModalOpen = ref(false);

const notesForm = useForm({
    platform_notes: props.organization.platform_notes ?? '',
});

const suspendForm = useForm({
    reason: '',
});

const reactivateForm = useForm({});
const removeOverrideForm = useForm({});

const planForm = useForm({
    plan_id: props.organization.plan_id ?? '',
});

const overrideForm = useForm({
    reason: '',
    expires_at: '',
    limits: { max_members: '', max_clients: '', max_storage_mb: '', max_portal_accesses: '' },
    features: { portal: false, audit: false },
});

const extendTrialForm = useForm({ days: 7 });
const subscriptionPlanForm = useForm({ plan_id: props.organization.plan_id ?? '' });
const subscriptionActionForm = useForm({});

const memberColumns = [
    { key: 'name', label: 'Nome' },
    { key: 'email', label: 'E-mail' },
    { key: 'role', label: 'Papel' },
];

const auditColumns = [
    { key: 'action', label: 'Ação' },
    { key: 'admin', label: 'Admin' },
    { key: 'created_at', label: 'Quando' },
];

function saveNotes() {
    notesForm.patch(`/platform/organizations/${props.organization.id}/notes`, { preserveScroll: true });
}

function suspendOrganization() {
    suspendForm.post(`/platform/organizations/${props.organization.id}/suspend`, {
        preserveScroll: true,
        onSuccess: () => {
            suspendModalOpen.value = false;
            suspendForm.reset();
        },
    });
}

function reactivateOrganization() {
    reactivateForm.post(`/platform/organizations/${props.organization.id}/reactivate`, { preserveScroll: true });
}

function savePlan() {
    planForm.patch(`/platform/organizations/${props.organization.id}/plan`, { preserveScroll: true });
}

function saveOverride() {
    overrideForm.post(`/platform/organizations/${props.organization.id}/overrides`, { preserveScroll: true });
}

function removeOverride() {
    if (!props.activeOverride) {
        return;
    }

    removeOverrideForm.delete(`/platform/organizations/${props.organization.id}/overrides/${props.activeOverride.id}`, { preserveScroll: true });
}

function extendTrial() {
    extendTrialForm.post(`/platform/organizations/${props.organization.id}/subscription/extend-trial`, { preserveScroll: true });
}

function changeSubscriptionPlan() {
    subscriptionPlanForm.post(`/platform/organizations/${props.organization.id}/subscription/change-plan`, { preserveScroll: true });
}

function runSubscriptionAction(action) {
    subscriptionActionForm.post(`/platform/organizations/${props.organization.id}/subscription/${action}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Platform · ${organization.name}`" />
    <PlatformLayout :title="organization.name" active-nav="organizations" :breadcrumbs="[{ label: 'Organizações', href: '/platform/organizations' }, { label: organization.name }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <StatusPill :status="organization.status" />
                    <p class="text-sm text-slate-500">Criada em {{ organization.created_at }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="organization.status === 'active'" variant="danger" @click="suspendModalOpen = true">Suspender</Button>
                    <Button v-else variant="secondary" @click="reactivateOrganization">Reativar</Button>
                    <Link href="/platform/organizations"><Button variant="secondary">Voltar</Button></Link>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <Card title="Dados cadastrais">
                    <dl class="grid gap-3 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Documento</dt><dd class="font-medium">{{ organization.document || '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">E-mail</dt><dd class="font-medium">{{ organization.email || '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Telefone</dt><dd class="font-medium">{{ organization.phone || '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Fuso horário</dt><dd class="font-medium">{{ organization.timezone }}</dd></div>
                    </dl>
                </Card>

                <Card title="Métricas">
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-slate-500">Membros ativos</dt><dd class="text-2xl font-semibold">{{ metrics.active_members_count }}</dd></div>
                        <div><dt class="text-slate-500">Total membros</dt><dd class="text-2xl font-semibold">{{ metrics.members_count }}</dd></div>
                        <div><dt class="text-slate-500">Clientes</dt><dd class="text-2xl font-semibold">{{ metrics.clients_count }}</dd></div>
                        <div><dt class="text-slate-500">Cobranças abertas</dt><dd class="text-2xl font-semibold">{{ metrics.open_receivables_count }}</dd></div>
                    </dl>
                </Card>
            </div>

            <Card v-if="subscription" title="Assinatura">
                <dl class="mb-4 grid gap-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-slate-500">Status</dt><dd class="font-semibold capitalize">{{ subscription.status.replace('_', ' ') }}</dd></div>
                    <div><dt class="text-slate-500">Plano</dt><dd class="font-semibold">{{ subscription.plan_name || '—' }}</dd></div>
                    <div v-if="subscription.trial_ends_at"><dt class="text-slate-500">Trial até</dt><dd>{{ subscription.trial_ends_at }}</dd></div>
                    <div v-if="subscription.trial_days_left !== null"><dt class="text-slate-500">Dias restantes</dt><dd>{{ subscription.trial_days_left }}</dd></div>
                    <div v-if="subscription.current_period_end"><dt class="text-slate-500">Período até</dt><dd>{{ subscription.current_period_end }}</dd></div>
                    <div v-if="subscription.past_due_at"><dt class="text-slate-500">Inadimplente desde</dt><dd>{{ subscription.past_due_at }}</dd></div>
                </dl>
                <form class="mb-4 grid gap-3 sm:grid-cols-[1fr_auto]" @submit.prevent="changeSubscriptionPlan">
                    <SelectInput id="subscription-plan" v-model="subscriptionPlanForm.plan_id" label="Alterar plano da assinatura" :options="planOptions" :error="subscriptionPlanForm.errors.plan_id" />
                    <div class="flex items-end"><Button type="submit" size="sm">Aplicar</Button></div>
                </form>
                <form class="mb-4 grid gap-3 sm:grid-cols-[1fr_auto]" @submit.prevent="extendTrial">
                    <TextInput id="extend-days" v-model="extendTrialForm.days" type="number" label="Estender trial (dias)" :error="extendTrialForm.errors.days" />
                    <div class="flex items-end"><Button type="submit" size="sm" variant="secondary">Estender</Button></div>
                </form>
                <div class="flex flex-wrap gap-2">
                    <Button size="sm" variant="secondary" @click="runSubscriptionAction('activate')">Ativar</Button>
                    <Button size="sm" variant="secondary" @click="runSubscriptionAction('mark-past-due')">Marcar inadimplente</Button>
                    <Button size="sm" variant="secondary" @click="runSubscriptionAction('pause')">Pausar</Button>
                    <Button size="sm" variant="danger" @click="runSubscriptionAction('cancel')">Cancelar</Button>
                </div>
            </Card>

            <Card v-if="planSummary" title="Plano e uso">
                <p class="mb-3 text-sm text-slate-600">Plano atual: <strong>{{ planSummary.plan.name }}</strong></p>
                <form class="mb-4 grid gap-3 sm:grid-cols-[1fr_auto]" @submit.prevent="savePlan">
                    <SelectInput id="org-plan" v-model="planForm.plan_id" label="Alterar plano" :options="planOptions" :error="planForm.errors.plan_id" />
                    <div class="flex items-end"><Button type="submit" size="sm">Aplicar plano</Button></div>
                </form>
                <div class="grid gap-2 text-sm">
                    <div v-for="item in planSummary.limits" :key="item.key" class="flex justify-between">
                        <span>{{ item.label }}</span>
                        <span>{{ item.current }} / {{ item.unlimited ? '∞' : item.limit }}</span>
                    </div>
                </div>
            </Card>

            <Card title="Override temporário">
                <div v-if="activeOverride" class="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                    <p>Override ativo{{ activeOverride.expires_at ? ` até ${activeOverride.expires_at}` : '' }}.</p>
                    <p v-if="activeOverride.reason" class="mt-1">{{ activeOverride.reason }}</p>
                    <Button class="mt-2" size="sm" variant="secondary" @click="removeOverride">Remover override</Button>
                </div>
                <form class="grid gap-3" @submit.prevent="saveOverride">
                    <TextInput id="override-reason" v-model="overrideForm.reason" label="Motivo" />
                    <TextInput id="override-expires" v-model="overrideForm.expires_at" type="datetime-local" label="Expira em (opcional)" />
                    <TextInput id="override-members" v-model="overrideForm.limits.max_members" type="number" label="Override max membros" />
                    <div class="flex justify-end"><Button type="submit" size="sm">Salvar override</Button></div>
                </form>
            </Card>

            <Card title="Notas internas (platform)">
                <form class="grid gap-3" @submit.prevent="saveNotes">
                    <TextareaInput id="platform-notes" v-model="notesForm.platform_notes" label="Visível apenas para admins da plataforma" :error="notesForm.errors.platform_notes" />
                    <div class="flex justify-end">
                        <Button type="submit" :disabled="notesForm.processing">Salvar notas</Button>
                    </div>
                </form>
            </Card>

            <DataTable :columns="memberColumns" :rows="members" empty-title="Nenhum membro ativo">
                <template #toolbar><div class="border-b border-slate-200 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Membros ativos</h2></div></template>
            </DataTable>

            <DataTable :columns="auditColumns" :rows="recentAuditLogs" empty-title="Nenhum evento registrado">
                <template #toolbar><div class="border-b border-slate-200 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Auditoria platform (recente)</h2></div></template>
                <template #cell-action="{ row }"><span class="font-mono text-xs">{{ row.action }}</span></template>
            </DataTable>
        </div>

        <Modal v-if="suspendModalOpen" open title="Suspender organização" @close="suspendModalOpen = false">
            <p class="text-sm text-slate-600">A organização <strong>{{ organization.name }}</strong> ficará com status suspenso e perderá acesso imediato.</p>
            <form class="mt-4 grid gap-3" @submit.prevent="suspendOrganization">
                <TextInput id="suspend-reason" v-model="suspendForm.reason" label="Motivo (opcional)" :error="suspendForm.errors.reason" />
            </form>
            <template #footer>
                <Button variant="secondary" @click="suspendModalOpen = false">Cancelar</Button>
                <Button variant="danger" :disabled="suspendForm.processing" @click="suspendOrganization">Confirmar suspensão</Button>
            </template>
        </Modal>
    </PlatformLayout>
</template>
