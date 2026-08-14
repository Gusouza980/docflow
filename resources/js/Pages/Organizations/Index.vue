<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import DataTable from '../../Components/Data/DataTable.vue';
import Button from '../../Components/UI/Button.vue';
import Modal from '../../Components/Overlays/Modal.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import TextareaInput from '../../Components/Forms/TextareaInput.vue';

const props = defineProps({
    organizations: { type: Array, default: () => [] },
    activeOrganizationId: { type: Number, default: null },
});

const page = usePage();
const createModalOpen = ref(false);
const editingOrganizationId = ref(null);

const columns = [
    { key: 'name', label: 'Organização' },
    { key: 'status', label: 'Status' },
    { key: 'members_count', label: 'Membros' },
    { key: 'pending_invitations_count', label: 'Convites' },
    { key: 'actions', label: '' },
];

const createForm = useForm({
    name: '',
    document: '',
    email: '',
    phone: '',
    timezone: 'America/Sao_Paulo',
});

const editForm = useForm({
    name: '',
    document: '',
    email: '',
    phone: '',
    timezone: 'America/Sao_Paulo',
    payment_instructions: '',
});

const gatewayForm = useForm({
    api_key: '',
    webhook_token: '',
});

const activeOrganization = computed(() => props.organizations.find((organization) => organization.active));

async function copyWebhookUrl() {
    const url = activeOrganization.value?.payment_gateway?.webhook_url;
    if (!url || !navigator.clipboard) {
        return;
    }

    await navigator.clipboard.writeText(url);
}

function savePaymentGateway() {
    if (!activeOrganization.value) {
        return;
    }

    gatewayForm.put(`/organizations/${activeOrganization.value.id}/payment-gateway`, {
        preserveScroll: true,
        onSuccess: () => gatewayForm.reset(),
    });
}

const selectedOrganization = computed(() => props.organizations.find((organization) => organization.id === editingOrganizationId.value));

watch(selectedOrganization, (organization) => {
    if (!organization) {
        editForm.reset();
        return;
    }

    editForm.defaults({
        name: organization.name ?? '',
        document: organization.document ?? '',
        email: organization.email ?? '',
        phone: organization.phone ?? '',
        timezone: organization.timezone ?? 'America/Sao_Paulo',
        payment_instructions: organization.payment_instructions ?? '',
    });
    editForm.reset();
});

function createOrganization() {
    createForm.post('/organizations', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset('name', 'document', 'email', 'phone');
            createModalOpen.value = false;
        },
    });
}

function openCreateModal() {
    createForm.clearErrors();
    createModalOpen.value = true;
}

function closeCreateModal() {
    if (createForm.processing) {
        return;
    }

    createForm.clearErrors();
    createModalOpen.value = false;
}

function editOrganization(organization) {
    editForm.clearErrors();
    editingOrganizationId.value = organization.id;
}

function closeEditModal() {
    if (editForm.processing) {
        return;
    }

    editForm.clearErrors();
    editingOrganizationId.value = null;
}

function updateOrganization() {
    if (!selectedOrganization.value) {
        return;
    }

    editForm.patch(`/organizations/${selectedOrganization.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editForm.clearErrors();
            editingOrganizationId.value = null;
        },
    });
}
</script>

<template>
    <Head title="Organizações" />
    <AppLayout title="Organizações" active-nav="organizations" :breadcrumbs="[{ label: 'Organizações' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <DataTable :columns="columns" :rows="organizations" empty-title="Nenhuma organização cadastrada">
                <template #toolbar>
                    <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-950">Workspaces</h2>
                            <p class="mt-1 text-xs text-slate-500">Gerencie organizações e selecione o contexto ativo da sessão.</p>
                        </div>
                        <div class="flex gap-2">
                            <Link v-if="page.props.auth?.permissions?.can_manage_organization && activeOrganizationId" href="/organizations/plan" class="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-[13px] font-semibold text-slate-800 hover:bg-slate-50">Plano e uso</Link>
                            <Link v-if="page.props.auth?.permissions?.can_manage_organization && activeOrganizationId" href="/organizations/billing" class="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-[13px] font-semibold text-slate-800 hover:bg-slate-50">Billing</Link>
                            <Button size="sm" @click="openCreateModal">Nova organização</Button>
                        </div>
                    </div>
                </template>
                <template #cell-name="{ row }">
                    <div class="min-w-56">
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-slate-950">{{ row.name }}</p>
                            <span v-if="row.active" class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">Ativa</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ row.email || 'Sem e-mail' }} · {{ row.timezone }}</p>
                    </div>
                </template>
                <template #cell-status="{ row }">
                    <StatusPill :status="row.status" />
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end gap-2">
                        <Link
                            v-if="!row.active"
                            :href="`/organizations/${row.id}/switch`"
                            method="post"
                            as="button"
                            class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-[13px] font-semibold text-slate-800 hover:bg-slate-50"
                        >
                            Selecionar
                        </Link>
                        <Button v-if="row.can_update" size="sm" variant="secondary" @click="editOrganization(row)">Editar</Button>
                    </div>
                </template>
            </DataTable>

            <div
                v-if="activeOrganization?.can_update"
                class="rounded-lg border border-slate-200 bg-white p-5"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Cobrança no portal (Asaas)</h2>
                        <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-600">
                            Use a chave da <strong>conta Asaas do escritório</strong>, não a do Docflow.
                            Depois, gere o Pix em Financeiro — o cliente paga em Cobranças no portal.
                        </p>
                    </div>
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="activeOrganization.payment_gateway?.connected ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                    >
                        {{ activeOrganization.payment_gateway?.connected ? 'Conectado' : 'Não conectado' }}
                    </span>
                </div>
                <form class="mt-4 grid gap-4 md:grid-cols-2" @submit.prevent="savePaymentGateway">
                    <TextInput
                        id="asaas-api-key"
                        v-model="gatewayForm.api_key"
                        label="Chave de API"
                        :placeholder="activeOrganization.payment_gateway?.masked_api_key || '$aact_...'"
                        :error="gatewayForm.errors.api_key"
                    />
                    <TextInput
                        id="asaas-webhook-token"
                        v-model="gatewayForm.webhook_token"
                        label="Token do webhook"
                        placeholder="O mesmo token configurado no Asaas"
                        :error="gatewayForm.errors.webhook_token"
                    />
                    <div class="md:col-span-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">URL do webhook</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <code class="break-all text-xs text-violet-700">{{ activeOrganization.payment_gateway?.webhook_url }}</code>
                            <Button type="button" size="sm" variant="secondary" @click="copyWebhookUrl">Copiar URL</Button>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">No Asaas, cadastre esta URL e o token acima. Eventos: pagamento confirmado / recebido.</p>
                    </div>
                    <div>
                        <Button type="submit" :loading="gatewayForm.processing">Salvar Asaas</Button>
                    </div>
                </form>
            </div>
        </div>

        <Modal open title="Nova organização" description="Crie um novo workspace e torne-o ativo para sua sessão." @close="closeCreateModal" v-if="createModalOpen">
            <form id="create-organization-form" class="grid gap-4" @submit.prevent="createOrganization">
                <TextInput id="create-name" v-model="createForm.name" label="Nome" required :error="createForm.errors.name" />
                <TextInput id="create-document" v-model="createForm.document" label="Documento" :error="createForm.errors.document" />
                <TextInput id="create-email" v-model="createForm.email" type="email" label="E-mail" :error="createForm.errors.email" />
                <TextInput id="create-phone" v-model="createForm.phone" label="Telefone" :error="createForm.errors.phone" />
                <TextInput id="create-timezone" v-model="createForm.timezone" label="Fuso horário" required :error="createForm.errors.timezone" />
            </form>
            <template #actions>
                <Button type="submit" form="create-organization-form" :loading="createForm.processing" :disabled="createForm.processing">Criar organização</Button>
            </template>
        </Modal>

        <Modal v-if="selectedOrganization" open title="Editar organização" :description="selectedOrganization.name" @close="closeEditModal">
            <form id="edit-organization-form" class="grid gap-4" @submit.prevent="updateOrganization">
                <TextInput id="edit-name" v-model="editForm.name" label="Nome" required :error="editForm.errors.name" />
                <TextInput id="edit-document" v-model="editForm.document" label="Documento" :error="editForm.errors.document" />
                <TextInput id="edit-email" v-model="editForm.email" type="email" label="E-mail" :error="editForm.errors.email" />
                <TextInput id="edit-phone" v-model="editForm.phone" label="Telefone" :error="editForm.errors.phone" />
                <TextInput id="edit-timezone" v-model="editForm.timezone" label="Fuso horário" required :error="editForm.errors.timezone" />
                <TextareaInput id="edit-payment-instructions" v-model="editForm.payment_instructions" label="Instruções de pagamento (portal)" :error="editForm.errors.payment_instructions" />
            </form>
            <template #actions>
                <Button type="submit" form="edit-organization-form" :loading="editForm.processing" :disabled="editForm.processing">Salvar alterações</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
