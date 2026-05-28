<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import DataTable from '../../Components/Data/DataTable.vue';
import Modal from '../../Components/Overlays/Modal.vue';
import Button from '../../Components/UI/Button.vue';
import Card from '../../Components/UI/Card.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';
import TextareaInput from '../../Components/Forms/TextareaInput.vue';

const props = defineProps({
    metrics: { type: Object, required: true },
    accesses: { type: Array, default: () => [] },
    messages: { type: Array, default: () => [] },
    tickets: { type: Array, default: () => [] },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const accessModalOpen = ref(false);
const messageModalOpen = ref(false);
const ticketModalOpen = ref(false);
const withEmpty = (items, label = 'Selecione') => [{ value: '', label }, ...items];
const selectedTemplate = computed(() => props.options.templates.find((template) => template.value === Number(messageForm.message_template_id)));

const accessColumns = [{ key: 'client', label: 'Cliente' }, { key: 'contact', label: 'Contato' }, { key: 'status', label: 'Status' }, { key: 'actions', label: '' }];
const messageColumns = [{ key: 'subject', label: 'Mensagem' }, { key: 'channel', label: 'Canal' }, { key: 'status', label: 'Status' }];
const ticketColumns = [{ key: 'title', label: 'Chamado' }, { key: 'status', label: 'Status' }, { key: 'priority', label: 'Prioridade' }];

const accessForm = useForm({ client_id: '', name: '', email: '', expires_at: '' });
const messageForm = useForm({ client_id: '', message_template_id: '', channel: 'email', subject: '', body: '', create_ticket: false });
const ticketForm = useForm({ client_id: '', assigned_to_member_id: '', title: '', description: '', priority: 'normal', visible_to_client: true, due_at: '' });

function submitAccess() {
    accessForm.post('/portal/accesses', { preserveScroll: true, onSuccess: () => accessModalOpen.value = false });
}

function submitMessage() {
    messageForm.post('/portal/messages', { preserveScroll: true, onSuccess: () => messageModalOpen.value = false });
}

function submitTicket() {
    ticketForm.post('/portal/tickets', { preserveScroll: true, onSuccess: () => ticketModalOpen.value = false });
}

function revokeAccess(access) {
    useForm({}).patch(`/portal/accesses/${access.id}/revoke`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Portal do cliente" />
    <AppLayout title="Portal do cliente" active-nav="portal" :breadcrumbs="[{ label: 'Portal' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.portal_url" tone="info">Link criado: {{ page.props.flash.portal_url }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <div class="grid gap-4 md:grid-cols-4">
                <Card title="Acessos ativos"><p class="text-2xl font-semibold text-slate-950">{{ metrics.active_accesses }}</p></Card>
                <Card title="Mensagens"><p class="text-2xl font-semibold text-slate-950">{{ metrics.messages }}</p></Card>
                <Card title="Chamados abertos"><p class="text-2xl font-semibold text-slate-950">{{ metrics.open_tickets }}</p></Card>
                <Card title="Consentimentos"><p class="text-2xl font-semibold text-slate-950">{{ metrics.consents }}</p></Card>
            </div>

            <div v-if="can.manage" class="flex flex-wrap justify-end gap-2">
                <Button variant="secondary" @click="accessModalOpen = true">Novo acesso</Button>
                <Button variant="secondary" @click="ticketModalOpen = true">Novo chamado</Button>
                <Button @click="messageModalOpen = true">Enviar mensagem</Button>
            </div>

            <DataTable :columns="accessColumns" :rows="accesses" empty-title="Nenhum acesso criado">
                <template #toolbar><div class="border-b border-slate-200 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Acessos do portal</h2></div></template>
                <template #cell-client="{ row }"><span class="font-semibold text-slate-950">{{ row.client.name }}</span></template>
                <template #cell-contact="{ row }"><div><p>{{ row.name }}</p><p class="text-xs text-slate-500">{{ row.email }}</p></div></template>
                <template #cell-status="{ row }"><StatusPill :status="row.status" /></template>
                <template #cell-actions="{ row }"><div class="flex justify-end"><Button v-if="row.status === 'active'" size="sm" variant="danger" @click="revokeAccess(row)">Revogar</Button></div></template>
            </DataTable>

            <div class="grid gap-4 xl:grid-cols-2">
                <DataTable :columns="messageColumns" :rows="messages" empty-title="Nenhuma mensagem registrada">
                    <template #toolbar><div class="border-b border-slate-200 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Comunicação recente</h2></div></template>
                    <template #cell-subject="{ row }"><div class="min-w-64"><p class="font-semibold text-slate-950">{{ row.subject || row.client.name }}</p><p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ row.body }}</p></div></template>
                    <template #cell-status="{ row }"><StatusPill :status="row.status" /></template>
                </DataTable>
                <DataTable :columns="ticketColumns" :rows="tickets" empty-title="Nenhum chamado encontrado">
                    <template #toolbar><div class="border-b border-slate-200 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Chamados</h2></div></template>
                    <template #cell-title="{ row }"><div class="min-w-64"><p class="font-semibold text-slate-950">{{ row.title }}</p><p class="mt-1 text-xs text-slate-500">{{ row.client.name }} · {{ row.assigned_to || 'Sem responsável' }}</p></div></template>
                    <template #cell-status="{ row }"><StatusPill :status="row.status" /></template>
                </DataTable>
            </div>
        </div>

        <Modal v-if="accessModalOpen" open title="Acesso ao portal" @close="accessModalOpen = false">
            <form id="access-form" class="grid gap-4" @submit.prevent="submitAccess">
                <SelectInput id="access-client" v-model="accessForm.client_id" label="Cliente" :options="withEmpty(options.clients)" :error="accessForm.errors.client_id" />
                <TextInput id="access-name" v-model="accessForm.name" label="Nome do contato" required :error="accessForm.errors.name" />
                <TextInput id="access-email" v-model="accessForm.email" type="email" label="E-mail" required :error="accessForm.errors.email" />
                <TextInput id="access-expires" v-model="accessForm.expires_at" type="date" label="Expira em" :error="accessForm.errors.expires_at" />
            </form>
            <template #actions><Button type="submit" form="access-form" :loading="accessForm.processing">Salvar</Button></template>
        </Modal>

        <Modal v-if="messageModalOpen" open title="Mensagem ao cliente" @close="messageModalOpen = false">
            <form id="message-form" class="grid gap-4" @submit.prevent="submitMessage">
                <SelectInput id="message-client" v-model="messageForm.client_id" label="Cliente" :options="withEmpty(options.clients)" :error="messageForm.errors.client_id" />
                <SelectInput id="message-template" v-model="messageForm.message_template_id" label="Modelo" :options="withEmpty(options.templates, 'Sem modelo')" :error="messageForm.errors.message_template_id" />
                <SelectInput id="message-channel" v-model="messageForm.channel" label="Canal" :options="[{ value: 'email', label: 'E-mail' }, { value: 'whatsapp', label: 'WhatsApp' }, { value: 'phone', label: 'Telefone' }, { value: 'portal', label: 'Portal' }]" :error="messageForm.errors.channel" />
                <TextInput id="message-subject" v-model="messageForm.subject" label="Assunto" :error="messageForm.errors.subject" />
                <TextareaInput id="message-body" v-model="messageForm.body" :placeholder="selectedTemplate?.body" label="Mensagem" :error="messageForm.errors.body" />
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700"><input v-model="messageForm.create_ticket" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-300" />Criar chamado a partir da mensagem</label>
            </form>
            <template #actions><Button type="submit" form="message-form" :loading="messageForm.processing">Salvar</Button></template>
        </Modal>

        <Modal v-if="ticketModalOpen" open title="Chamado" @close="ticketModalOpen = false">
            <form id="ticket-form" class="grid gap-4" @submit.prevent="submitTicket">
                <SelectInput id="ticket-client" v-model="ticketForm.client_id" label="Cliente" :options="withEmpty(options.clients)" :error="ticketForm.errors.client_id" />
                <TextInput id="ticket-title" v-model="ticketForm.title" label="Título" required :error="ticketForm.errors.title" />
                <TextareaInput id="ticket-description" v-model="ticketForm.description" label="Descrição" :error="ticketForm.errors.description" />
                <div class="grid gap-4 sm:grid-cols-3"><SelectInput id="ticket-assignee" v-model="ticketForm.assigned_to_member_id" label="Responsável" :options="withEmpty(options.members, 'Sem responsável')" :error="ticketForm.errors.assigned_to_member_id" /><SelectInput id="ticket-priority" v-model="ticketForm.priority" label="Prioridade" :options="[{ value: 'low', label: 'Baixa' }, { value: 'normal', label: 'Normal' }, { value: 'high', label: 'Alta' }]" /><TextInput id="ticket-due" v-model="ticketForm.due_at" type="date" label="Prazo" /></div>
            </form>
            <template #actions><Button type="submit" form="ticket-form" :loading="ticketForm.processing">Salvar</Button></template>
        </Modal>
    </AppLayout>
</template>
