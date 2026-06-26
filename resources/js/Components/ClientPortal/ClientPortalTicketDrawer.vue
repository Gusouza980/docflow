<script setup>
import { nextTick, onUnmounted, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Alert from '../Feedback/Alert.vue';
import Modal from '../Overlays/Modal.vue';
import Badge from '../UI/Badge.vue';
import Button from '../UI/Button.vue';
import StarRating from '../UI/StarRating.vue';
import StatusPill from '../UI/StatusPill.vue';
import TextareaInput from '../Forms/TextareaInput.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    ticketId: { type: Number, default: null },
});

const emit = defineEmits(['close']);

const loading = ref(false);
const ticket = ref(null);
const threadContainer = ref(null);
let pollTimer = null;

const replyForm = useForm({ body: '', attachment: null });
const ratingForm = useForm({ rating: 0, comment: '' });

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

function scrollThreadToBottom() {
    nextTick(() => {
        if (threadContainer.value) {
            threadContainer.value.scrollTop = threadContainer.value.scrollHeight;
        }
    });
}

function mergeMessages(incoming) {
    if (!ticket.value || !incoming.length) {
        return;
    }

    const knownIds = new Set(ticket.value.messages.map((message) => message.id));
    const merged = [...ticket.value.messages];

    incoming.forEach((message) => {
        if (!knownIds.has(message.id)) {
            merged.push(message);
        }
    });

    ticket.value.messages = merged.sort((a, b) => a.id - b.id);
}

async function loadTicket() {
    if (!props.ticketId) {
        ticket.value = null;

        return;
    }

    loading.value = true;

    try {
        const response = await fetch(`/client-portal/tickets/${props.ticketId}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            ticket.value = null;

            return;
        }

        const data = await response.json();
        ticket.value = data.ticket;
        scrollThreadToBottom();
    } finally {
        loading.value = false;
    }
}

async function pollMessages() {
    if (!props.open || !props.ticketId) {
        return;
    }

    const lastId = ticket.value?.messages?.at(-1)?.id;
    const url = lastId
        ? `/client-portal/tickets/${props.ticketId}/messages/poll?since_id=${lastId}`
        : `/client-portal/tickets/${props.ticketId}/messages/poll`;

    try {
        const response = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        if (!ticket.value) {
            return;
        }

        if (!ticket.value.messages?.length) {
            ticket.value.messages = data.messages ?? [];
        } else {
            mergeMessages(data.messages ?? []);
        }

        scrollThreadToBottom();
    } catch {
        // ignore polling errors
    }
}

function startPolling() {
    stopPolling();
    pollTimer = window.setInterval(pollMessages, 5000);
}

function stopPolling() {
    if (pollTimer) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
}

watch(() => [props.open, props.ticketId], () => {
    if (props.open && props.ticketId) {
        loadTicket().then(() => startPolling());
    } else {
        stopPolling();
        ticket.value = null;
    }
}, { immediate: true });

onUnmounted(stopPolling);

function submitReply() {
    replyForm.post(`/client-portal/tickets/${props.ticketId}/messages`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            replyForm.reset();
            loadTicket();
        },
    });
}

function onAttachmentChange(event) {
    replyForm.attachment = event.target.files?.[0] ?? null;
}

function formatFileSize(bytes) {
    if (!bytes) {
        return '';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function submitRating() {
    if (!ratingForm.rating) {
        return;
    }

    ratingForm.post(`/client-portal/tickets/${props.ticketId}/rating`, {
        preserveScroll: true,
        onSuccess: () => {
            ratingForm.reset();
            loadTicket();
        },
    });
}

function closeDrawer() {
    emit('close');
}
</script>

<template>
    <Modal
        v-if="open && ticketId"
        open
        :title="ticket?.title ?? 'Solicitação'"
        :description="ticket ? `Aberta em ${ticket.created_at}` : 'Carregando...'"
        @close="closeDrawer"
    >
        <div v-if="loading" class="py-12 text-center text-sm text-slate-500">Carregando solicitação...</div>

        <div v-else-if="ticket" class="grid gap-5">
            <div class="flex flex-wrap items-center gap-2">
                <StatusPill :status="ticket.status" />
                <Badge v-if="ticket.needs_response" tone="warning">Aguardando sua resposta</Badge>
                <span class="text-xs text-slate-500">Atualizado em {{ ticket.updated_at }}</span>
            </div>

            <Alert v-if="ticket.needs_response" tone="warning">
                A equipe solicitou mais informações. Responda abaixo para continuarmos o atendimento.
            </Alert>

            <p v-if="ticket.description" class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">{{ ticket.description }}</p>

            <section class="overflow-hidden rounded-lg border border-slate-200">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                    <h3 class="text-sm font-semibold text-slate-950">Conversa sobre esta solicitação</h3>
                </div>
                <div ref="threadContainer" class="flex max-h-72 flex-col gap-3 overflow-y-auto bg-white p-4">
                    <p v-if="!ticket.messages.length" class="text-center text-sm text-slate-500">Nenhuma mensagem nesta solicitação.</p>
                    <div
                        v-for="message in ticket.messages"
                        :key="message.id"
                        :class="['flex', message.sender_type === 'client' ? 'justify-end' : 'justify-start']"
                    >
                        <div
                            :class="[
                                'max-w-[85%] rounded-2xl px-3 py-2 text-sm shadow-sm',
                                message.sender_type === 'client'
                                    ? 'rounded-br-sm bg-blue-100 text-blue-950'
                                    : 'rounded-bl-sm border border-slate-200 bg-slate-50 text-slate-900',
                            ]"
                        >
                            <p class="mb-1 text-xs font-semibold">{{ message.sender_name }}</p>
                            <p v-if="message.body" class="whitespace-pre-wrap leading-6">{{ message.body }}</p>
                            <div v-if="message.attachments?.length" class="mt-2 grid gap-1">
                                <a
                                    v-for="attachment in message.attachments"
                                    :key="attachment.id"
                                    :href="attachment.download_url"
                                    class="inline-flex items-center gap-2 rounded-lg bg-white/70 px-2 py-1 text-xs font-medium text-emerald-900 underline-offset-2 hover:underline"
                                >
                                    {{ attachment.original_name }}
                                    <span class="opacity-70">({{ formatFileSize(attachment.size) }})</span>
                                </a>
                            </div>
                            <p class="mt-1 text-right text-[11px] opacity-70">{{ formatMessageTime(message.created_at) }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <form v-if="ticket.can_reply" class="grid gap-3" @submit.prevent="submitReply">
                <TextareaInput id="portal-ticket-reply-body" v-model="replyForm.body" label="Sua mensagem" :error="replyForm.errors.body" />
                <label class="grid gap-2">
                    <span class="text-sm font-medium text-slate-900">Anexo (opcional)</span>
                    <input
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                        class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-emerald-800"
                        @change="onAttachmentChange"
                    />
                    <span v-if="replyForm.errors.attachment" class="text-xs font-medium text-red-600">{{ replyForm.errors.attachment }}</span>
                </label>
                <div class="flex justify-end">
                    <Button type="submit" :loading="replyForm.processing" :disabled="!replyForm.body.trim() && !replyForm.attachment">Enviar mensagem</Button>
                </div>
            </form>

            <p v-else-if="!ticket.is_finalized" class="text-sm text-slate-500">Este chamado está encerrado e não aceita novas mensagens.</p>

            <section v-if="ticket.can_rate" class="grid gap-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
                <div>
                    <h3 class="text-sm font-semibold text-amber-950">Como foi o atendimento?</h3>
                    <p class="mt-1 text-sm text-amber-900">Este chamado foi finalizado. Avalie de 1 a 5 estrelas e, se quiser, deixe um comentário.</p>
                </div>
                <StarRating
                    v-model="ratingForm.rating"
                    label="Sua nota"
                    :error="ratingForm.errors.rating"
                />
                <TextareaInput
                    id="portal-ticket-rating-comment"
                    v-model="ratingForm.comment"
                    label="Observação (opcional)"
                    :rows="3"
                    :error="ratingForm.errors.comment"
                />
                <div class="flex justify-end">
                    <Button type="button" :loading="ratingForm.processing" :disabled="!ratingForm.rating" @click="submitRating">
                        Enviar avaliação
                    </Button>
                </div>
            </section>

            <section v-else-if="ticket.rating" class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
                <div>
                    <h3 class="text-sm font-semibold text-slate-950">Sua avaliação</h3>
                    <p class="mt-1 text-xs text-slate-500">Enviada em {{ ticket.rating.rated_at }}</p>
                </div>
                <StarRating :model-value="ticket.rating.score" readonly />
                <p v-if="ticket.rating.comment" class="text-sm text-slate-700">{{ ticket.rating.comment }}</p>
            </section>
        </div>

        <template #actions>
            <Button variant="ghost" @click="closeDrawer">Fechar</Button>
        </template>
    </Modal>
</template>
