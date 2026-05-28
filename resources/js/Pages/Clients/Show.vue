<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Button from '../../Components/UI/Button.vue';
import Badge from '../../Components/UI/Badge.vue';
import Card from '../../Components/UI/Card.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';
import Modal from '../../Components/Overlays/Modal.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';
import TextareaInput from '../../Components/Forms/TextareaInput.vue';
import CheckboxInput from '../../Components/Forms/CheckboxInput.vue';

const props = defineProps({
    client: { type: Object, required: true },
    timeline: { type: Array, default: () => [] },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const editModalOpen = ref(false);
const statusModalOpen = ref(false);
const contactModalOpen = ref(false);
const tagModalOpen = ref(false);

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
    potential_revenue_cents: props.client.potential_revenue_cents ?? '',
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

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
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
                                <dd class="mt-1 text-sm text-slate-900">{{ client.potential_revenue_cents ?? 'Restrito' }}</dd>
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
                                <p class="mt-1 text-xs text-slate-500">{{ event.created_at }}</p>
                            </div>
                        </div>
                        <p v-else class="text-sm text-slate-500">Nenhum evento registrado.</p>
                    </Card>
                </div>
            </div>
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
