<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Button from '../../Components/UI/Button.vue';
import Badge from '../../Components/UI/Badge.vue';
import Card from '../../Components/UI/Card.vue';
import DataTable from '../../Components/Data/DataTable.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';
import Modal from '../../Components/Overlays/Modal.vue';
import Tabs from '../../Components/Navigation/Tabs.vue';
import ClientTicketDrawer from '../../Components/Clients/ClientTicketDrawer.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';
import TextareaInput from '../../Components/Forms/TextareaInput.vue';
import CheckboxInput from '../../Components/Forms/CheckboxInput.vue';
import { formatBrlFromCents } from '../../lib/money';

const props = defineProps({
    client: { type: Object, required: true },
    timeline: { type: Array, default: () => [] },
    tab: { type: String, default: 'overview' },
    selectedTicketId: { type: Number, default: null },
    filters: { type: Object, default: () => ({ ticket_filter: 'open' }) },
    hub: { type: Object, required: true },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const activeTab = ref(props.tab);
const editModalOpen = ref(false);
const statusModalOpen = ref(false);
const contactModalOpen = ref(false);
const tagModalOpen = ref(false);
const portalAccessModalOpen = ref(false);
const ticketCreateModalOpen = ref(false);
const activeTicketId = ref(props.selectedTicketId);
const ticketFilterForm = useForm({
    ticket_filter: props.filters.ticket_filter ?? 'open',
});
const chatContainer = ref(null);
const chatMessages = ref([...(props.hub.communications?.messages ?? [])]);
let pollTimer = null;

const hubTabs = computed(() => [
    { value: 'overview', label: 'Visão geral' },
    ...(page.props.auth?.permissions?.can_access_crm ? [{ value: 'commercial', label: 'Comercial' }] : []),
    { value: 'services', label: 'Serviços' },
    { value: 'contracts', label: 'Contratos' },
    { value: 'communication', label: 'Comunicação' },
    { value: 'documents', label: 'Documentos' },
    { value: 'requests', label: 'Solicitações' },
    { value: 'tickets', label: 'Chamados' },
    { value: 'portal', label: 'Portal' },
    { value: 'activity', label: 'Atividade' },
]);

const serviceForm = useForm({
    service_type_id: '',
    status: 'active',
    starts_at: '',
    ends_at: '',
    notes: '',
});

function submitService() {
    serviceForm.post(`/clients/${props.client.id}/services`, {
        preserveScroll: true,
        onSuccess: () => serviceForm.reset('notes', 'ends_at'),
    });
}

const money = (cents) => {
    if (cents === null || cents === undefined) {
        return '—';
    }

    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(cents) / 100);
};

const documentColumns = [{ key: 'title', label: 'Documento' }, { key: 'status', label: 'Status' }, { key: 'expires_at', label: 'Vencimento' }];
const requestColumns = [{ key: 'title', label: 'Solicitação' }, { key: 'status', label: 'Status' }, { key: 'due_at', label: 'Prazo' }];
const ticketColumns = [
    { key: 'title', label: 'Chamado' },
    { key: 'status', label: 'Status' },
    { key: 'priority', label: 'Prioridade' },
    { key: 'due_at', label: 'Prazo' },
    { key: 'assigned_to', label: 'Responsável' },
    { key: 'actions', label: '' },
];

const ticketCreateForm = useForm({
    title: '',
    description: '',
    priority: 'normal',
    assigned_to_member_id: '',
    due_at: '',
    visible_to_client: true,
});
const portalColumns = [{ key: 'name', label: 'Contato' }, { key: 'email', label: 'E-mail' }, { key: 'status', label: 'Status' }, { key: 'has_password', label: 'Senha' }, { key: 'actions', label: '' }];

const messageForm = useForm({
    channel: 'portal',
    subject: '',
    body: '',
    message_template_id: '',
});

const portalAccessForm = useForm({
    client_id: props.client.id,
    name: '',
    email: '',
    expires_at: '',
});

const statusOptions = [
    { value: 'active', label: 'Ativo' },
    { value: 'inactive', label: 'Inativo' },
    { value: 'negotiation', label: 'Negociação' },
    { value: 'delinquent', label: 'Inadimplente' },
    { value: 'closed', label: 'Encerrado' },
];
const priorityOptions = [
    { value: 'low', label: 'Baixa' },
    { value: 'normal', label: 'Normal' },
    { value: 'high', label: 'Alta' },
];
const riskOptions = [
    { value: 'low', label: 'Baixo' },
    { value: 'medium', label: 'Médio' },
    { value: 'high', label: 'Alto' },
];
const accessOptions = [
    { value: 'all_members', label: 'Todos os membros' },
    { value: 'restricted', label: 'Restrito' },
];
const contactTypeOptions = [
    { value: 'general', label: 'Geral' },
    { value: 'financial', label: 'Financeiro' },
    { value: 'operational', label: 'Operacional' },
];

const editForm = useForm({
    display_name: props.client.display_name ?? '',
    document_number: props.client.document_number ?? '',
    priority: props.client.priority ?? 'normal',
    risk_level: props.client.risk_level ?? 'low',
    potential_revenue_cents: formatBrlFromCents(props.client.potential_revenue_cents),
    origin: props.client.origin ?? '',
    access_policy: props.client.access_policy ?? 'all_members',
    internal_notes: props.client.internal_notes ?? '',
    entered_at: props.client.entered_at ?? '',
    responsible_member_ids: props.client.responsibles.map((member) => member.id),
    individual_profile: {
        full_name: props.client.individual_profile?.full_name ?? '',
        rg: props.client.individual_profile?.rg ?? '',
        birth_date: props.client.individual_profile?.birth_date ?? '',
        marital_status: props.client.individual_profile?.marital_status ?? '',
        profession: props.client.individual_profile?.profession ?? '',
    },
    company_profile: {
        legal_name: props.client.company_profile?.legal_name ?? '',
        trade_name: props.client.company_profile?.trade_name ?? '',
        state_registration: props.client.company_profile?.state_registration ?? '',
        municipal_registration: props.client.company_profile?.municipal_registration ?? '',
        tax_regime: props.client.company_profile?.tax_regime ?? '',
        main_cnae: props.client.company_profile?.main_cnae ?? '',
    },
});

const statusForm = useForm({
    status: props.client.status,
    closure_reason: props.client.closure_reason ?? '',
});

const contactForm = useForm({
    name: '',
    role: '',
    email: '',
    phone: '',
    whatsapp: '',
    type: 'general',
    is_primary: false,
    notes: '',
});

const tagForm = useForm({
    name: '',
    color: '#0f766e',
});

const availableTags = computed(() => {
    const applied = new Set(props.client.tags.map((tag) => tag.id));

    return props.options.tags.filter((tag) => !applied.has(tag.id));
});

function toggleEditResponsible(id) {
    const index = editForm.responsible_member_ids.indexOf(id);

    if (index === -1) {
        editForm.responsible_member_ids.push(id);
        return;
    }

    editForm.responsible_member_ids.splice(index, 1);
}

function openEditModal() {
    editForm.clearErrors();
    editModalOpen.value = true;
}

function submitEdit() {
    editForm.patch(`/clients/${props.client.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editModalOpen.value = false;
        },
    });
}

function submitStatus() {
    statusForm.patch(`/clients/${props.client.id}/status`, {
        preserveScroll: true,
        onSuccess: () => {
            statusModalOpen.value = false;
        },
    });
}

function submitContact() {
    contactForm.post(`/clients/${props.client.id}/contacts`, {
        preserveScroll: true,
        onSuccess: () => {
            contactForm.reset();
            contactModalOpen.value = false;
        },
    });
}

function submitTag() {
    tagForm.post('/client-tags', {
        preserveScroll: true,
        onSuccess: () => {
            tagForm.reset('name');
            tagModalOpen.value = false;
        },
    });
}

function switchTab(tab) {
    activeTab.value = tab;
    router.get(`/clients/${props.client.id}`, { tab, ticket_filter: ticketFilterForm.ticket_filter }, { preserveState: true, preserveScroll: true, replace: true });
}

function applyTicketFilter() {
    router.get(`/clients/${props.client.id}`, { tab: 'tickets', ticket_filter: ticketFilterForm.ticket_filter }, { preserveState: true, preserveScroll: true });
}

function openTicket(ticketId) {
    activeTicketId.value = ticketId;
    router.get(`/clients/${props.client.id}`, {
        tab: 'tickets',
        ticket_filter: ticketFilterForm.ticket_filter,
        ticket: ticketId,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function closeTicketDrawer() {
    activeTicketId.value = null;
    router.get(`/clients/${props.client.id}`, {
        tab: 'tickets',
        ticket_filter: ticketFilterForm.ticket_filter,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function submitTicketCreate() {
    ticketCreateForm.post(`/clients/${props.client.id}/tickets`, {
        preserveScroll: true,
        onSuccess: () => {
            ticketCreateForm.reset();
            ticketCreateModalOpen.value = false;
        },
    });
}

watch(() => props.selectedTicketId, (ticketId) => {
    activeTicketId.value = ticketId;
});

function formatMessageTime(iso) {
    if (!iso) {
        return '';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}

function scrollChatToBottom() {
    nextTick(() => {
        if (chatContainer.value) {
            chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
        }
    });
}

function mergeMessages(incoming) {
    if (!incoming.length) {
        return;
    }

    const knownIds = new Set(chatMessages.value.map((message) => message.id));
    const merged = [...chatMessages.value];

    incoming.forEach((message) => {
        if (!knownIds.has(message.id)) {
            merged.push(message);
        }
    });

    chatMessages.value = merged.sort((a, b) => a.id - b.id);
}

async function pollMessages() {
    if (activeTab.value !== 'communication') {
        return;
    }

    const lastId = chatMessages.value.at(-1)?.id;
    const url = lastId
        ? `/clients/${props.client.id}/messages/poll?since_id=${lastId}`
        : `/clients/${props.client.id}/messages/poll`;

    try {
        const response = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        mergeMessages(data.messages ?? []);
        scrollChatToBottom();
    } catch {
        // ignore polling errors
    }
}

onMounted(() => {
    scrollChatToBottom();
    pollTimer = window.setInterval(pollMessages, 5000);
});

onUnmounted(() => {
    if (pollTimer) {
        window.clearInterval(pollTimer);
    }
});

watch(() => props.hub.communications?.messages, (messages) => {
    chatMessages.value = [...(messages ?? [])];
    scrollChatToBottom();
}, { deep: true });

watch(activeTab, (tab) => {
    if (tab === 'communication') {
        scrollChatToBottom();
    }
});

function submitMessage() {
    messageForm.post(`/clients/${props.client.id}/messages`, {
        preserveScroll: true,
        onSuccess: () => {
            messageForm.reset('body', 'subject');
            pollMessages();

            if (page.props.flash?.whatsapp_url) {
                window.open(page.props.flash.whatsapp_url, '_blank', 'noopener');
            }
        },
    });
}

function openWhatsApp(message) {
    if (message.whatsapp_url) {
        window.open(message.whatsapp_url, '_blank', 'noopener');
    }

    router.post(`/clients/${props.client.id}/messages/${message.id}/whatsapp`, {}, { preserveScroll: true });
}

function openTicketFromMessage(message) {
    router.post(`/clients/${props.client.id}/messages/${message.id}/ticket`, {}, { preserveScroll: true });
}

function submitPortalAccess() {
    portalAccessForm.post(`/clients/${props.client.id}/portal-accesses`, {
        preserveScroll: true,
        onSuccess: () => {
            portalAccessForm.reset('name', 'email', 'expires_at');
            portalAccessModalOpen.value = false;
        },
    });
}

function copyPortalUrl() {
    const url = page.props.flash?.portal_url;

    if (url && navigator.clipboard) {
        navigator.clipboard.writeText(url);
    }
}
</script>

<template>
    <Head :title="client.display_name" />
    <AppLayout :title="client.display_name" active-nav="clients" :breadcrumbs="[{ label: 'Clientes', href: '/clients' }, { label: client.display_name }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <StatusPill :status="client.status" />
                        <Badge tone="primary">{{ client.type === 'company' ? 'Pessoa jurídica' : 'Pessoa física' }}</Badge>
                        <Badge v-if="client.access_policy === 'restricted'" tone="warning">Restrito</Badge>
                    </div>
                    <p class="mt-2 text-sm text-slate-500">{{ client.document_number || 'Sem documento' }}</p>
                </div>
                <div v-if="can.update" class="flex flex-wrap gap-2">
                    <Button variant="secondary" @click="statusModalOpen = true">Alterar status</Button>
                    <Button @click="openEditModal">Editar ficha</Button>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-5">
                <Card title="Documentos"><p class="text-2xl font-semibold text-slate-950">{{ hub.metrics.documents }}</p></Card>
                <Card title="Solicitações"><p class="text-2xl font-semibold text-slate-950">{{ hub.metrics.document_requests }}</p></Card>
                <Card title="Chamados abertos"><p class="text-2xl font-semibold text-slate-950">{{ hub.metrics.open_tickets }}</p></Card>
                <Card title="Mensagens"><p class="text-2xl font-semibold text-slate-950">{{ hub.metrics.messages }}</p></Card>
                <Card title="Acessos portal"><p class="text-2xl font-semibold text-slate-950">{{ hub.metrics.portal_accesses }}</p></Card>
            </div>

            <Tabs :tabs="hubTabs" :model-value="activeTab" @update:model-value="switchTab">
                <div v-if="activeTab === 'overview'" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="grid gap-4">
                    <Card title="Dados cadastrais">
                        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <dt class="text-xs font-semibold uppercase text-slate-500">Prioridade</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ client.priority }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase text-slate-500">Risco</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ client.risk_level }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase text-slate-500">Origem</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ client.origin || 'Não informado' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase text-slate-500">Entrada</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ client.entered_at || 'Não informada' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase text-slate-500">Receita potencial</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ client.potential_revenue_cents == null ? 'Restrito' : money(client.potential_revenue_cents) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase text-slate-500">Responsável principal</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ client.primary_responsible?.name ?? 'Sem responsável' }}</dd>
                            </div>
                        </dl>
                    </Card>

                    <Card title="Contatos">
                        <template #actions>
                            <Button v-if="can.update" size="sm" variant="secondary" @click="contactModalOpen = true">Adicionar contato</Button>
                        </template>
                        <div v-if="client.contacts.length" class="grid gap-3">
                            <div v-for="contact in client.contacts" :key="contact.id" class="rounded-lg border border-slate-200 p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-semibold text-slate-950">{{ contact.name }}</p>
                                            <Badge v-if="contact.is_primary" tone="success">Principal</Badge>
                                            <Badge tone="neutral">{{ contact.type }}</Badge>
                                        </div>
                                        <p class="mt-1 text-sm text-slate-500">{{ contact.role || 'Sem função' }}</p>
                                        <p class="mt-2 text-sm text-slate-700">{{ contact.email || 'Sem e-mail' }} · {{ contact.phone || contact.whatsapp || 'Sem telefone' }}</p>
                                    </div>
                                    <Link
                                        v-if="can.update"
                                        :href="`/client-contacts/${contact.id}`"
                                        method="delete"
                                        as="button"
                                        class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-[13px] font-semibold text-slate-800 hover:bg-slate-50"
                                    >
                                        Remover
                                    </Link>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-slate-500">Nenhum contato cadastrado.</p>
                    </Card>
                </div>

                <div class="grid content-start gap-4">
                    <Card title="Responsáveis">
                        <div class="grid gap-2">
                            <div v-for="member in client.responsibles" :key="member.id" class="rounded-lg border border-slate-200 px-3 py-2">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-slate-950">{{ member.name }}</p>
                                    <Badge v-if="member.is_primary" tone="primary">Principal</Badge>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">{{ member.email }}</p>
                            </div>
                        </div>
                    </Card>

                    <Card title="Etiquetas">
                        <template #actions>
                            <Button v-if="can.update" size="sm" variant="secondary" @click="tagModalOpen = true">Nova</Button>
                        </template>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="tag in client.tags" :key="tag.id" class="inline-flex items-center gap-2 rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-700 ring-1 ring-inset ring-teal-200">
                                {{ tag.name }}
                                <Link v-if="can.update" :href="`/clients/${client.id}/tags/${tag.id}`" method="delete" as="button" class="text-teal-900">×</Link>
                            </span>
                            <span v-if="!client.tags.length" class="text-sm text-slate-500">Sem etiquetas.</span>
                        </div>
                        <div v-if="can.update && availableTags.length" class="mt-4 grid gap-2">
                            <p class="text-xs font-semibold uppercase text-slate-500">Aplicar etiqueta</p>
                            <div class="flex flex-wrap gap-2">
                                <Link
                                    v-for="tag in availableTags"
                                    :key="tag.id"
                                    :href="`/clients/${client.id}/tags/${tag.id}`"
                                    method="post"
                                    as="button"
                                    class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    {{ tag.name }}
                                </Link>
                            </div>
                        </div>
                    </Card>

                    <Card title="Linha do tempo">
                        <div v-if="timeline.length" class="grid gap-3">
                            <div v-for="event in timeline" :key="event.id" class="border-l-2 border-blue-200 pl-3">
                                <p class="text-sm font-semibold text-slate-900">{{ event.action }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ event.user_name ? `${event.user_name} · ` : '' }}{{ event.created_at }}</p>
                            </div>
                        </div>
                        <p v-else class="text-sm text-slate-500">Nenhum evento registrado.</p>
                    </Card>
                </div>
                </div>

                <div v-else-if="activeTab === 'commercial'" class="grid gap-4">
                    <Card title="Histórico comercial (CRM)">
                        <div v-if="hub.commercial?.length" class="grid gap-3">
                            <div v-for="lead in hub.commercial" :key="lead.id" class="rounded-lg border border-slate-200 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p class="font-semibold text-slate-950">{{ lead.name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ lead.stage_label }} · {{ lead.origin_label || 'Sem origem' }} · convertido em {{ lead.converted_at || '—' }}</p>
                                    </div>
                                    <Link :href="lead.href" class="text-sm font-semibold text-slate-800 underline">Abrir lead</Link>
                                </div>
                                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <p class="text-xs font-semibold uppercase text-slate-500">Atividades</p>
                                        <ul class="mt-1 grid gap-1">
                                            <li v-for="activity in lead.activities" :key="activity.id" class="text-sm text-slate-700">{{ activity.type_label }}: {{ activity.body }}</li>
                                            <li v-if="!lead.activities.length" class="text-xs text-slate-400">Sem atividades</li>
                                        </ul>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold uppercase text-slate-500">Propostas</p>
                                        <ul class="mt-1 grid gap-1">
                                            <li v-for="proposal in lead.proposals" :key="proposal.id" class="text-sm text-slate-700">{{ proposal.title }} · {{ proposal.status_label }}</li>
                                            <li v-if="!lead.proposals.length" class="text-xs text-slate-400">Sem propostas</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-slate-500">Nenhum lead convertido vinculado a este cliente.</p>
                    </Card>
                </div>

                <div v-else-if="activeTab === 'services'" class="grid gap-4">
                    <Card title="Serviços do cliente">
                        <ul v-if="hub.services?.length" class="mb-4 grid gap-2">
                            <li v-for="service in hub.services" :key="service.id" class="rounded-lg border border-slate-200 px-3 py-2">
                                <p class="font-semibold text-slate-950">{{ service.service_type_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ service.status_label }} · {{ service.starts_at || '—' }} → {{ service.ends_at || 'em aberto' }}
                                    <span v-if="service.assignee_name"> · {{ service.assignee_name }}</span>
                                </p>
                                <p v-if="service.notes" class="mt-1 text-sm text-slate-600">{{ service.notes }}</p>
                            </li>
                        </ul>
                        <p v-else class="mb-4 text-sm text-slate-500">Nenhum serviço vinculado.</p>

                        <form v-if="can.update" class="grid gap-3 border-t border-slate-200 pt-4" @submit.prevent="submitService">
                            <p class="text-sm font-semibold text-slate-900">Vincular serviço</p>
                            <SelectInput
                                id="client-service-type"
                                v-model="serviceForm.service_type_id"
                                label="Tipo de serviço"
                                :options="options.service_types"
                                required
                                :error="serviceForm.errors.service_type_id"
                            />
                            <SelectInput
                                id="client-service-status"
                                v-model="serviceForm.status"
                                label="Status"
                                :options="options.service_statuses"
                                :error="serviceForm.errors.status"
                            />
                            <TextInput id="client-service-starts" v-model="serviceForm.starts_at" type="date" label="Início" :error="serviceForm.errors.starts_at" />
                            <TextInput id="client-service-ends" v-model="serviceForm.ends_at" type="date" label="Término" :error="serviceForm.errors.ends_at" />
                            <TextareaInput id="client-service-notes" v-model="serviceForm.notes" label="Notas" :error="serviceForm.errors.notes" />
                            <div class="flex justify-end">
                                <Button type="submit" size="sm" :disabled="serviceForm.processing">Salvar serviço</Button>
                            </div>
                        </form>
                    </Card>
                </div>

                <div v-else-if="activeTab === 'contracts'" class="grid gap-4">
                    <Card title="Contratos do cliente">
                        <ul v-if="hub.contracts?.length" class="grid gap-2">
                            <li v-for="contract in hub.contracts" :key="contract.id" class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-2">
                                <div>
                                    <p class="font-semibold text-slate-950">{{ contract.code }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ contract.status_label }} · {{ money(contract.amount_cents) }} · {{ contract.billing_interval_label }}
                                        · {{ contract.starts_at || '—' }} → {{ contract.ends_at || 'sem término' }}
                                    </p>
                                </div>
                                <Link :href="contract.href" class="text-sm font-semibold text-slate-800 underline">Abrir</Link>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-slate-500">Nenhum contrato. Crie em <Link href="/contracts" class="underline">Contratos</Link>.</p>
                    </Card>
                </div>

                <div v-else-if="activeTab === 'communication'" class="grid gap-4">
                    <div class="flex justify-end">
                        <Link href="/messages/batch"><Button variant="secondary" size="sm">Envio em lote</Button></Link>
                    </div>
                    <Alert v-if="!hub.communications.has_portal_consent" tone="warning">
                        Este cliente ainda não autorizou comunicação pelo portal. Mensagens outbound pelo canal portal exigem consentimento.
                    </Alert>
                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center gap-3 border-b border-slate-200 bg-blue-700 px-4 py-3 text-white">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold">C</div>
                            <div>
                                <h2 class="text-sm font-semibold">Conversa com o cliente</h2>
                                <p class="text-xs text-blue-100">{{ client.display_name }}</p>
                            </div>
                        </div>
                        <div ref="chatContainer" class="flex max-h-[28rem] min-h-[20rem] flex-col gap-3 overflow-y-auto bg-slate-100 px-4 py-5">
                            <p v-if="!chatMessages.length" class="mx-auto max-w-sm rounded-lg bg-white px-4 py-3 text-center text-sm text-slate-600 shadow-sm">
                                Nenhuma mensagem registrada para este cliente.
                            </p>
                            <div v-for="message in chatMessages" :key="message.id" :class="['flex', message.direction === 'outbound' ? 'justify-end' : 'justify-start']">
                                <div :class="['max-w-[85%] rounded-2xl px-3 py-2 shadow-sm sm:max-w-[70%]', message.direction === 'outbound' ? 'rounded-br-sm bg-blue-100 text-blue-950' : 'rounded-bl-sm border border-slate-200 bg-white text-slate-900']">
                                    <p class="mb-1 text-xs font-semibold text-slate-500">{{ message.sender_name }} · {{ message.channel }}</p>
                                    <p v-if="message.subject" class="mb-1 text-sm font-semibold">{{ message.subject }}</p>
                                    <p class="whitespace-pre-wrap text-sm leading-6">{{ message.body }}</p>
                                    <div class="mt-1 flex items-center justify-between gap-2">
                                        <StatusPill v-if="message.status" :status="message.status" />
                                        <p class="text-right text-[11px] text-slate-500">{{ formatMessageTime(message.created_at) }}</p>
                                    </div>
                                    <button
                                        v-if="message.can_open_whatsapp && can.update"
                                        type="button"
                                        class="mt-2 text-xs font-semibold text-blue-700 underline underline-offset-2 hover:text-blue-900"
                                        @click="openWhatsApp(message)"
                                    >
                                        Abrir WhatsApp
                                    </button>
                                    <button
                                        v-if="message.can_open_ticket && can.update"
                                        type="button"
                                        class="mt-2 text-xs font-semibold text-blue-700 underline underline-offset-2 hover:text-blue-900"
                                        @click="openTicketFromMessage(message)"
                                    >
                                        Criar chamado
                                    </button>
                                </div>
                            </div>
                        </div>
                        <form v-if="can.update" class="border-t border-slate-200 bg-white p-4" @submit.prevent="submitMessage">
                            <div class="grid gap-3 sm:grid-cols-3">
                                <SelectInput id="hub-message-channel" v-model="messageForm.channel" label="Canal" :options="options.message_channels" :error="messageForm.errors.channel" />
                                <SelectInput id="hub-message-template" v-model="messageForm.message_template_id" label="Modelo" :options="[{ value: '', label: 'Sem modelo' }, ...options.message_templates]" :error="messageForm.errors.message_template_id" />
                                <TextInput id="hub-message-subject" v-model="messageForm.subject" label="Assunto" :error="messageForm.errors.subject" />
                            </div>
                            <TextareaInput id="hub-message-body" v-model="messageForm.body" label="Mensagem" class="mt-3" :error="messageForm.errors.body" />
                            <div class="mt-3 flex justify-end">
                                <Button type="submit" :loading="messageForm.processing">Enviar mensagem</Button>
                            </div>
                        </form>
                    </section>
                </div>

                <div v-else-if="activeTab === 'documents'">
                    <DataTable :columns="documentColumns" :rows="hub.documents" empty-title="Nenhum documento vinculado">
                        <template #cell-title="{ row }"><Link :href="row.href" class="font-semibold text-slate-950 hover:text-blue-700">{{ row.title }}</Link><p class="mt-1 text-xs text-slate-500">{{ row.category || 'Sem categoria' }}</p></template>
                        <template #cell-status="{ row }"><StatusPill :status="row.status" /></template>
                    </DataTable>
                </div>

                <div v-else-if="activeTab === 'requests'">
                    <DataTable :columns="requestColumns" :rows="hub.document_requests" empty-title="Nenhuma solicitação documental">
                        <template #cell-title="{ row }"><Link :href="row.href" class="font-semibold text-slate-950 hover:text-blue-700">{{ row.title }}</Link></template>
                        <template #cell-status="{ row }"><StatusPill :status="row.status" /></template>
                    </DataTable>
                </div>

                <div v-else-if="activeTab === 'tickets'" class="grid gap-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <form class="flex flex-wrap items-end gap-3" @submit.prevent="applyTicketFilter">
                            <SelectInput
                                id="ticket-filter"
                                v-model="ticketFilterForm.ticket_filter"
                                label="Filtrar chamados"
                                :options="options.ticket_filters"
                            />
                            <Button type="submit" variant="secondary">Filtrar</Button>
                        </form>
                        <Button v-if="can.update" @click="ticketCreateModalOpen = true">Novo chamado</Button>
                    </div>
                    <DataTable :columns="ticketColumns" :rows="hub.tickets" empty-title="Nenhum chamado registrado">
                        <template #cell-title="{ row }">
                            <button type="button" class="text-left" @click="openTicket(row.id)">
                                <p class="font-semibold text-slate-950 hover:text-blue-700">{{ row.title }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ row.messages_count }} mensagens
                                    <span v-if="row.opened_by_portal"> · Portal</span>
                                </p>
                            </button>
                        </template>
                        <template #cell-status="{ row }"><StatusPill :status="row.status" /></template>
                        <template #cell-priority="{ row }"><Badge tone="secondary">{{ row.priority }}</Badge></template>
                        <template #cell-due_at="{ row }">
                            <span :class="row.is_overdue ? 'font-semibold text-red-700' : ''">{{ row.due_at || 'Sem prazo' }}</span>
                        </template>
                        <template #cell-assigned_to="{ row }">{{ row.assigned_to || 'Sem responsável' }}</template>
                        <template #cell-actions="{ row }">
                            <Button size="sm" variant="secondary" @click="openTicket(row.id)">Abrir</Button>
                        </template>
                    </DataTable>
                </div>

                <div v-else-if="activeTab === 'portal'" class="grid gap-4">
                    <Alert v-if="page.props.flash?.portal_url" tone="success">
                        Convite criado. Copie o link e envie ao cliente:
                        <button type="button" class="ml-2 font-semibold underline" @click="copyPortalUrl">Copiar link</button>
                    </Alert>
                    <div class="flex justify-end">
                        <Button v-if="can.update" @click="portalAccessModalOpen = true">Novo convite</Button>
                    </div>
                    <DataTable :columns="portalColumns" :rows="hub.portal_accesses" empty-title="Nenhum acesso ao portal">
                        <template #cell-name="{ row }"><p class="font-semibold text-slate-950">{{ row.name }}</p></template>
                        <template #cell-status="{ row }"><StatusPill :status="row.status" /></template>
                        <template #cell-has_password="{ row }"><Badge :tone="row.has_password ? 'success' : 'warning'">{{ row.has_password ? 'Configurada' : 'Pendente' }}</Badge></template>
                        <template #cell-actions="{ row }">
                            <Link
                                v-if="can.update && row.status === 'active'"
                                :href="`/clients/${client.id}/portal-accesses/${row.id}/revoke`"
                                method="patch"
                                as="button"
                                class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-[13px] font-semibold text-slate-800 hover:bg-slate-50"
                            >
                                Revogar
                            </Link>
                        </template>
                    </DataTable>
                </div>

                <div v-else-if="activeTab === 'activity'">
                    <Card title="Histórico de atividade">
                        <div v-if="timeline.length" class="grid gap-3">
                            <div v-for="event in timeline" :key="event.id" class="border-l-2 border-blue-200 pl-3">
                                <p class="text-sm font-semibold text-slate-900">{{ event.action }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ event.user_name ? `${event.user_name} · ` : '' }}{{ event.created_at }}</p>
                            </div>
                        </div>
                        <p v-else class="text-sm text-slate-500">Nenhum evento registrado.</p>
                    </Card>
                </div>
            </Tabs>
        </div>

        <Modal v-if="editModalOpen" open title="Editar ficha" :description="client.display_name" @close="editModalOpen = false">
            <form id="edit-client-form" class="grid gap-4" @submit.prevent="submitEdit">
                <TextInput id="edit-client-name" v-model="editForm.display_name" label="Nome de exibição" required :error="editForm.errors.display_name" />
                <TextInput id="edit-client-document" v-model="editForm.document_number" label="Documento" :error="editForm.errors.document_number" />
                <div class="grid gap-4 sm:grid-cols-3">
                    <SelectInput id="edit-client-priority" v-model="editForm.priority" label="Prioridade" :options="priorityOptions" :error="editForm.errors.priority" />
                    <SelectInput id="edit-client-risk" v-model="editForm.risk_level" label="Risco" :options="riskOptions" :error="editForm.errors.risk_level" />
                    <SelectInput id="edit-client-access" v-model="editForm.access_policy" label="Acesso" :options="accessOptions" :error="editForm.errors.access_policy" />
                </div>
                <div v-if="client.type === 'individual'" class="grid gap-4 sm:grid-cols-2">
                    <TextInput id="edit-client-full-name" v-model="editForm.individual_profile.full_name" label="Nome completo" required :error="editForm.errors['individual_profile.full_name']" />
                    <TextInput id="edit-client-profession" v-model="editForm.individual_profile.profession" label="Profissão" :error="editForm.errors['individual_profile.profession']" />
                </div>
                <div v-else class="grid gap-4 sm:grid-cols-2">
                    <TextInput id="edit-client-legal-name" v-model="editForm.company_profile.legal_name" label="Razão social" required :error="editForm.errors['company_profile.legal_name']" />
                    <TextInput id="edit-client-trade-name" v-model="editForm.company_profile.trade_name" label="Nome fantasia" :error="editForm.errors['company_profile.trade_name']" />
                </div>
                <div>
                    <p class="mb-2 text-sm font-semibold text-slate-700">Responsáveis</p>
                    <div class="grid gap-2">
                        <label v-for="member in options.members" :key="member.value" class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" :checked="editForm.responsible_member_ids.includes(member.value)" @change="toggleEditResponsible(member.value)" />
                            <span>{{ member.label }}</span>
                        </label>
                    </div>
                    <p v-if="editForm.errors.responsible_member_ids" class="mt-2 text-sm font-medium text-red-600">{{ editForm.errors.responsible_member_ids }}</p>
                </div>
                <TextareaInput id="edit-client-notes" v-model="editForm.internal_notes" label="Notas internas" :error="editForm.errors.internal_notes" />
            </form>
            <template #actions>
                <Button type="submit" form="edit-client-form" :loading="editForm.processing" :disabled="editForm.processing">Salvar alterações</Button>
            </template>
        </Modal>

        <Modal v-if="statusModalOpen" open title="Alterar status" :description="client.display_name" @close="statusModalOpen = false">
            <form id="status-client-form" class="grid gap-4" @submit.prevent="submitStatus">
                <SelectInput id="client-status" v-model="statusForm.status" label="Status" :options="statusOptions" :error="statusForm.errors.status" />
                <TextInput v-if="statusForm.status === 'closed'" id="client-closure-reason" v-model="statusForm.closure_reason" label="Motivo do encerramento" :error="statusForm.errors.closure_reason" />
            </form>
            <template #actions>
                <Button type="submit" form="status-client-form" :loading="statusForm.processing" :disabled="statusForm.processing">Atualizar status</Button>
            </template>
        </Modal>

        <Modal v-if="contactModalOpen" open title="Novo contato" :description="client.display_name" @close="contactModalOpen = false">
            <form id="contact-client-form" class="grid gap-4" @submit.prevent="submitContact">
                <TextInput id="contact-name" v-model="contactForm.name" label="Nome" required :error="contactForm.errors.name" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <SelectInput id="contact-type" v-model="contactForm.type" label="Tipo" :options="contactTypeOptions" :error="contactForm.errors.type" />
                    <TextInput id="contact-role" v-model="contactForm.role" label="Função" :error="contactForm.errors.role" />
                </div>
                <TextInput id="contact-email" v-model="contactForm.email" type="email" label="E-mail" :error="contactForm.errors.email" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <TextInput id="contact-phone" v-model="contactForm.phone" label="Telefone" :error="contactForm.errors.phone" />
                    <TextInput id="contact-whatsapp" v-model="contactForm.whatsapp" label="WhatsApp" :error="contactForm.errors.whatsapp" />
                </div>
                <CheckboxInput v-model="contactForm.is_primary" label="Contato principal para este tipo" />
                <TextareaInput id="contact-notes" v-model="contactForm.notes" label="Observações" :error="contactForm.errors.notes" />
            </form>
            <template #actions>
                <Button type="submit" form="contact-client-form" :loading="contactForm.processing" :disabled="contactForm.processing">Adicionar contato</Button>
            </template>
        </Modal>

        <ClientTicketDrawer
            :open="Boolean(activeTicketId)"
            :client-id="client.id"
            :ticket-id="activeTicketId"
            :can-update="can.update"
            :members="options.members"
            :status-options="options.ticket_statuses"
            :priority-options="options.ticket_priorities"
            @close="closeTicketDrawer"
        />

        <Modal v-if="ticketCreateModalOpen" open title="Novo chamado" :description="client.display_name" @close="ticketCreateModalOpen = false">
            <form id="ticket-create-form" class="grid gap-4" @submit.prevent="submitTicketCreate">
                <TextInput id="ticket-create-title" v-model="ticketCreateForm.title" label="Título" required :error="ticketCreateForm.errors.title" />
                <TextareaInput id="ticket-create-description" v-model="ticketCreateForm.description" label="Descrição" :error="ticketCreateForm.errors.description" />
                <div class="grid gap-4 sm:grid-cols-3">
                    <SelectInput id="ticket-create-priority" v-model="ticketCreateForm.priority" label="Prioridade" :options="options.ticket_priorities" :error="ticketCreateForm.errors.priority" />
                    <SelectInput id="ticket-create-assignee" v-model="ticketCreateForm.assigned_to_member_id" label="Responsável" :options="[{ value: '', label: 'Sem responsável' }, ...options.members]" :error="ticketCreateForm.errors.assigned_to_member_id" />
                    <TextInput id="ticket-create-due" v-model="ticketCreateForm.due_at" type="date" label="Prazo" :error="ticketCreateForm.errors.due_at" />
                </div>
                <CheckboxInput v-model="ticketCreateForm.visible_to_client" label="Visível ao cliente no portal" />
            </form>
            <template #actions>
                <Button type="submit" form="ticket-create-form" :loading="ticketCreateForm.processing">Criar chamado</Button>
            </template>
        </Modal>

        <Modal v-if="portalAccessModalOpen" open title="Convite do portal" :description="client.display_name" @close="portalAccessModalOpen = false">
            <form id="portal-access-form" class="grid gap-4" @submit.prevent="submitPortalAccess">
                <TextInput id="portal-access-name" v-model="portalAccessForm.name" label="Nome do contato" required :error="portalAccessForm.errors.name" />
                <TextInput id="portal-access-email" v-model="portalAccessForm.email" type="email" label="E-mail" required :error="portalAccessForm.errors.email" />
                <TextInput id="portal-access-expires" v-model="portalAccessForm.expires_at" type="date" label="Expiração do convite" :error="portalAccessForm.errors.expires_at" />
            </form>
            <template #actions>
                <Button type="submit" form="portal-access-form" :loading="portalAccessForm.processing">Gerar convite</Button>
            </template>
        </Modal>

        <Modal v-if="tagModalOpen" open title="Nova etiqueta" description="A etiqueta ficará disponível para clientes da organização ativa." @close="tagModalOpen = false">
            <form id="tag-client-form" class="grid gap-4" @submit.prevent="submitTag">
                <TextInput id="tag-name" v-model="tagForm.name" label="Nome" required :error="tagForm.errors.name" />
                <TextInput id="tag-color" v-model="tagForm.color" label="Cor" :error="tagForm.errors.color" />
            </form>
            <template #actions>
                <Button type="submit" form="tag-client-form" :loading="tagForm.processing" :disabled="tagForm.processing">Criar etiqueta</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
