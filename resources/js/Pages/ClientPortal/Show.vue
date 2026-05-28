<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { nextTick, onMounted, ref, watch } from 'vue';
import Alert from '../../Components/Feedback/Alert.vue';
import DataTable from '../../Components/Data/DataTable.vue';
import Modal from '../../Components/Overlays/Modal.vue';
import Badge from '../../Components/UI/Badge.vue';
import Button from '../../Components/UI/Button.vue';
import Card from '../../Components/UI/Card.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import TextareaInput from '../../Components/Forms/TextareaInput.vue';

const props = defineProps({
    token: { type: String, required: true },
    client: { type: Object, required: true },
    hasPortalCommunicationConsent: { type: Boolean, default: false },
    messages: { type: Array, default: () => [] },
    documentRequests: { type: Array, default: () => [] },
    receivables: { type: Array, default: () => [] },
    tickets: { type: Array, default: () => [] },
    announcements: { type: Array, default: () => [] },
    reports: { type: Array, default: () => [] },
});

const page = usePage();
const ticketModalOpen = ref(false);
const requestModalOpen = ref(false);
const selectedRequest = ref(null);
const chatContainer = ref(null);
const money = (cents) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format((cents ?? 0) / 100);
const requestColumns = [{ key: 'title', label: 'Solicitação' }, { key: 'status', label: 'Status' }, { key: 'due_at', label: 'Prazo' }];
const receivableColumns = [{ key: 'description', label: 'Cobrança' }, { key: 'status', label: 'Status' }, { key: 'amount', label: 'Valor' }];
const ticketColumns = [{ key: 'title', label: 'Solicitação' }, { key: 'status', label: 'Status' }];
const messageForm = useForm({ body: '' });
const consentForm = useForm({});
const ticketForm = useForm({ title: '', description: '' });

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

onMounted(scrollChatToBottom);
watch(() => props.messages, scrollChatToBottom, { deep: true });

function submitMessage() {
    if (!props.hasPortalCommunicationConsent || !messageForm.body.trim()) {
        return;
    }

    messageForm.post(`/client-portal/${props.token}/messages`, {
        preserveScroll: true,
        onSuccess: () => {
            messageForm.reset('body');
            scrollChatToBottom();
        },
    });
}

function grantPortalCommunicationConsent() {
    consentForm.post(`/client-portal/${props.token}/consent`, {
        preserveScroll: true,
    });
}

function handleMessageKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        submitMessage();
    }
}

function submitTicket() {
    ticketForm.post(`/client-portal/${props.token}/tickets`, { preserveScroll: true, onSuccess: () => ticketModalOpen.value = false });
}

function openRequestModal(request) {
    selectedRequest.value = request;
    requestModalOpen.value = true;
}

function closeRequestModal() {
    requestModalOpen.value = false;
    selectedRequest.value = null;
}
</script>

<template>
    <Head title="Portal do cliente" />
    <main class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white px-4 py-5 sm:px-6 lg:px-8">
            <div class="mx-auto flex max-w-6xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div><p class="text-sm text-slate-500">Portal do cliente</p><h1 class="text-2xl font-semibold text-slate-950">{{ client.name }}</h1></div>
                <div class="flex gap-2"><Button @click="ticketModalOpen = true">Abrir solicitação</Button></div>
            </div>
        </header>
        <section class="mx-auto grid max-w-6xl gap-4 p-4 sm:p-6 lg:p-8">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>
            <div class="grid gap-4 md:grid-cols-4">
                <Card title="Solicitações"><p class="text-2xl font-semibold text-slate-950">{{ documentRequests.length }}</p></Card>
                <Card title="Cobranças"><p class="text-2xl font-semibold text-slate-950">{{ receivables.length }}</p></Card>
                <Card title="Chamados"><p class="text-2xl font-semibold text-slate-950">{{ tickets.length }}</p></Card>
                <Card title="Relatórios"><p class="text-2xl font-semibold text-slate-950">{{ reports.length }}</p></Card>
            </div>

            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-200 bg-emerald-700 px-4 py-3 text-white">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-600 text-sm font-semibold">E</div>
                    <div>
                        <h2 class="text-sm font-semibold">Atendimento do escritório</h2>
                        <p class="text-xs text-emerald-100">Conversa com {{ client.contact.name }}</p>
                    </div>
                </div>

                <div class="relative">
                    <div
                        :class="[
                            'transition',
                            !hasPortalCommunicationConsent && 'pointer-events-none select-none blur-sm',
                        ]"
                    >
                        <div ref="chatContainer" class="flex max-h-[28rem] min-h-[20rem] flex-col gap-3 overflow-y-auto bg-[#e5ddd5] px-4 py-5">
                            <p v-if="!messages.length" class="mx-auto max-w-sm rounded-lg bg-white/80 px-4 py-3 text-center text-sm text-slate-600 shadow-sm">
                                Nenhuma mensagem ainda. Envie a primeira mensagem para falar com o escritório.
                            </p>

                            <div
                                v-for="message in messages"
                                :key="message.id"
                                :class="['flex', message.direction === 'inbound' ? 'justify-end' : 'justify-start']"
                            >
                                <div
                                    :class="[
                                        'max-w-[85%] rounded-2xl px-3 py-2 shadow-sm sm:max-w-[70%]',
                                        message.direction === 'inbound'
                                            ? 'rounded-br-sm bg-emerald-100 text-emerald-950'
                                            : 'rounded-bl-sm border border-slate-200 bg-white text-slate-900',
                                    ]"
                                >
                                    <p v-if="message.direction === 'outbound'" class="mb-1 text-xs font-semibold text-emerald-700">{{ message.sender_name }}</p>
                                    <p v-if="message.subject" class="mb-1 text-sm font-semibold">{{ message.subject }}</p>
                                    <p class="whitespace-pre-wrap text-sm leading-6">{{ message.body }}</p>
                                    <p
                                        :class="[
                                            'mt-1 text-right text-[11px]',
                                            message.direction === 'inbound' ? 'text-emerald-800/70' : 'text-slate-500',
                                        ]"
                                    >
                                        {{ formatMessageTime(message.created_at) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <form class="border-t border-slate-200 bg-slate-50 p-3" @submit.prevent="submitMessage">
                            <div class="flex items-end gap-2">
                                <label class="min-w-0 flex-1">
                                    <span class="sr-only">Mensagem</span>
                                    <textarea
                                        v-model="messageForm.body"
                                        rows="2"
                                        placeholder="Digite uma mensagem"
                                        :disabled="!hasPortalCommunicationConsent"
                                        class="max-h-32 min-h-11 w-full resize-none rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-300 disabled:bg-slate-100 disabled:text-slate-400"
                                        @keydown="handleMessageKeydown"
                                    />
                                    <span v-if="messageForm.errors.body" class="mt-1 block text-xs font-medium text-red-600">{{ messageForm.errors.body }}</span>
                                </label>
                                <Button type="submit" class="shrink-0" :loading="messageForm.processing" :disabled="!hasPortalCommunicationConsent || !messageForm.body.trim()">Enviar</Button>
                            </div>
                            <p class="mt-2 text-xs text-slate-500">Enter envia · Shift+Enter quebra linha</p>
                        </form>
                    </div>

                    <div
                        v-if="!hasPortalCommunicationConsent"
                        class="absolute inset-0 z-10 flex items-center justify-center bg-white/50 p-4 backdrop-blur-[1px]"
                    >
                        <div class="max-w-md rounded-lg border border-amber-200 bg-amber-50 p-5 text-center shadow-lg ring-1 ring-inset ring-amber-200">
                            <h3 class="text-base font-semibold text-amber-950">Comunicação pelo portal</h3>
                            <p class="mt-2 text-sm leading-6 text-amber-900">
                                Para enviar e receber mensagens com o escritório por aqui, autorize a comunicação pelo portal.
                            </p>
                            <Button class="mt-4" :loading="consentForm.processing" @click="grantPortalCommunicationConsent">
                                Autorizar comunicação
                            </Button>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid gap-4 xl:grid-cols-2">
                <DataTable :columns="requestColumns" :rows="documentRequests" empty-title="Nenhuma solicitação documental">
                    <template #cell-title="{ row }">
                        <button
                            type="button"
                            class="rounded-md text-left transition hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300"
                            @click="openRequestModal(row)"
                        >
                            <p class="font-semibold text-slate-950">{{ row.title }}</p>
                            <p class="text-xs text-slate-500">{{ row.received_items_count }} de {{ row.items_count }} itens recebidos</p>
                        </button>
                    </template>
                    <template #cell-status="{ row }"><StatusPill :status="row.status" /></template>
                </DataTable>
                <DataTable :columns="receivableColumns" :rows="receivables" empty-title="Nenhuma cobrança">
                    <template #cell-status="{ row }"><StatusPill :status="row.status" /></template>
                    <template #cell-amount="{ row }"><span>{{ money(row.balance_cents) }}</span><span class="block text-xs text-slate-500">de {{ money(row.amount_cents) }}</span></template>
                </DataTable>
                <DataTable :columns="ticketColumns" :rows="tickets" empty-title="Nenhuma solicitação aberta">
                    <template #cell-title="{ row }"><div><p class="font-semibold text-slate-950">{{ row.title }}</p><p class="text-xs text-slate-500">{{ row.messages_count }} mensagens</p></div></template>
                    <template #cell-status="{ row }"><StatusPill :status="row.status" /></template>
                </DataTable>
                <Card title="Comunicados">
                    <div class="grid gap-3">
                        <article v-for="announcement in announcements" :key="announcement.id" class="border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                            <h2 class="text-sm font-semibold text-slate-950">{{ announcement.title }}</h2>
                            <p class="mt-1 text-sm text-slate-600">{{ announcement.body }}</p>
                        </article>
                        <p v-if="!announcements.length" class="text-sm text-slate-500">Nenhum comunicado publicado.</p>
                    </div>
                </Card>
                <Card title="Relatórios liberados">
                    <div class="grid gap-3">
                        <article v-for="report in reports" :key="report.id" class="border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                            <h2 class="text-sm font-semibold text-slate-950">{{ report.title }}</h2>
                            <p class="mt-1 text-sm text-slate-600">Tarefas concluídas: {{ report.payload?.tasks?.completed ?? 0 }} · Chamados abertos: {{ report.payload?.tickets?.open ?? 0 }}</p>
                        </article>
                        <p v-if="!reports.length" class="text-sm text-slate-500">Nenhum relatório liberado.</p>
                    </div>
                </Card>
            </div>
        </section>

        <Modal v-if="ticketModalOpen" open title="Abrir solicitação" @close="ticketModalOpen = false">
            <form id="client-ticket-form" class="grid gap-4" @submit.prevent="submitTicket">
                <TextInput id="client-ticket-title" v-model="ticketForm.title" label="Título" required :error="ticketForm.errors.title" />
                <TextareaInput id="client-ticket-description" v-model="ticketForm.description" label="Descrição" required :error="ticketForm.errors.description" />
            </form>
            <template #actions><Button type="submit" form="client-ticket-form" :loading="ticketForm.processing">Salvar</Button></template>
        </Modal>

        <Modal
            v-if="requestModalOpen && selectedRequest"
            open
            :title="selectedRequest.title"
            :description="`${selectedRequest.received_items_count} de ${selectedRequest.items_count} itens recebidos`"
            @close="closeRequestModal"
        >
            <div class="grid gap-3">
                <article v-for="item in selectedRequest.items" :key="item.id" class="rounded-lg border border-slate-200 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-semibold text-slate-950">{{ item.title }}</h3>
                        <StatusPill :status="item.status" />
                        <Badge v-if="item.category" tone="secondary">{{ item.category.name }}</Badge>
                    </div>
                    <p class="mt-2 text-sm text-slate-600">{{ item.instructions || 'Sem instruções específicas' }}</p>
                    <p class="mt-2 text-sm text-slate-700">Prazo: {{ item.due_at || selectedRequest.due_at || 'Sem prazo' }}</p>
                    <p v-if="item.rejection_reason" class="mt-2 text-sm text-red-700">{{ item.rejection_reason }}</p>
                </article>
                <p v-if="!selectedRequest.items.length" class="text-sm text-slate-500">Nenhum item solicitado.</p>
            </div>
        </Modal>
    </main>
</template>
