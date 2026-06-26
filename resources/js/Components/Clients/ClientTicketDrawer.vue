<script setup>
import { nextTick, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '../Overlays/Modal.vue';
import Badge from '../UI/Badge.vue';
import Button from '../UI/Button.vue';
import CheckboxInput from '../Forms/CheckboxInput.vue';
import SelectInput from '../Forms/SelectInput.vue';
import StatusPill from '../UI/StatusPill.vue';
import StarRating from '../UI/StarRating.vue';
import TextInput from '../Forms/TextInput.vue';
import TextareaInput from '../Forms/TextareaInput.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    clientId: { type: Number, required: true },
    ticketId: { type: Number, default: null },
    canUpdate: { type: Boolean, default: false },
    members: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    priorityOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const loading = ref(false);
const ticket = ref(null);
const threadContainer = ref(null);
const replyVisibleToClient = ref(true);
const internalNote = ref(false);

const updateForm = useForm({
    status: '',
    priority: '',
    assigned_to_member_id: '',
    due_at: '',
    visible_to_client: true,
});

const replyForm = useForm({
    body: '',
    visible_to_client: true,
    attachment: null,
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

function scrollThreadToBottom() {
    nextTick(() => {
        if (threadContainer.value) {
            threadContainer.value.scrollTop = threadContainer.value.scrollHeight;
        }
    });
}

async function loadTicket() {
    if (! props.ticketId) {
        ticket.value = null;

        return;
    }

    loading.value = true;

    try {
        const response = await fetch(`/clients/${props.clientId}/tickets/${props.ticketId}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (! response.ok) {
            ticket.value = null;

            return;
        }

        const data = await response.json();
        ticket.value = data.ticket;
        updateForm.status = data.ticket.status;
        updateForm.priority = data.ticket.priority;
        updateForm.assigned_to_member_id = data.ticket.assigned_to_member_id ?? '';
        updateForm.due_at = data.ticket.due_at ?? '';
        updateForm.visible_to_client = data.ticket.visible_to_client;
        scrollThreadToBottom();
    } finally {
        loading.value = false;
    }
}

watch(() => [props.open, props.ticketId], () => {
    if (props.open && props.ticketId) {
        loadTicket();
    }
}, { immediate: true });

function submitUpdate() {
    updateForm.patch(`/clients/${props.clientId}/tickets/${props.ticketId}`, {
        preserveScroll: true,
        onSuccess: () => loadTicket(),
    });
}

function submitReply() {
    replyForm.visible_to_client = internalNote.value ? false : replyVisibleToClient.value;
    replyForm.post(`/clients/${props.clientId}/tickets/${props.ticketId}/messages`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            replyForm.reset('body', 'attachment');
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

function closeDrawer() {
    emit('close');
}
</script>

<template>
    <Modal
        v-if="open && ticketId"
        open
        :title="ticket?.title ?? 'Chamado'"
        :description="ticket?.opened_by_portal ? 'Aberto pelo portal do cliente' : 'Aberto pela equipe'"
        @close="closeDrawer"
    >
        <div v-if="loading" class="py-12 text-center text-sm text-slate-500">Carregando chamado...</div>

        <div v-else-if="ticket" class="grid gap-5">
            <div class="flex flex-wrap items-center gap-2">
                <StatusPill :status="ticket.status" />
                <Badge tone="secondary">{{ ticket.priority }}</Badge>
                <Badge v-if="!ticket.visible_to_client" tone="warning">Oculto do portal</Badge>
                <span v-if="ticket.due_at" class="text-xs text-slate-500">Prazo: {{ ticket.due_at }}</span>
            </div>

            <p v-if="ticket.description" class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">{{ ticket.description }}</p>

            <form v-if="canUpdate" class="grid gap-3 rounded-lg border border-slate-200 p-4" @submit.prevent="submitUpdate">
                <p class="text-sm font-semibold text-slate-950">Gestão do chamado</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <SelectInput id="ticket-detail-status" v-model="updateForm.status" label="Status" :options="statusOptions" :error="updateForm.errors.status" />
                    <SelectInput id="ticket-detail-priority" v-model="updateForm.priority" label="Prioridade" :options="priorityOptions" :error="updateForm.errors.priority" />
                    <SelectInput id="ticket-detail-assignee" v-model="updateForm.assigned_to_member_id" label="Responsável" :options="[{ value: '', label: 'Sem responsável' }, ...members]" :error="updateForm.errors.assigned_to_member_id" />
                    <TextInput id="ticket-detail-due" v-model="updateForm.due_at" type="date" label="Prazo" :error="updateForm.errors.due_at" />
                </div>
                <CheckboxInput v-model="updateForm.visible_to_client" label="Visível ao cliente no portal" :error="updateForm.errors.visible_to_client" />
                <div class="flex justify-end">
                    <Button type="submit" size="sm" variant="secondary" :loading="updateForm.processing">Salvar alterações</Button>
                </div>
            </form>

            <section class="overflow-hidden rounded-lg border border-slate-200">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                    <h3 class="text-sm font-semibold text-slate-950">Histórico do chamado</h3>
                </div>
                <div ref="threadContainer" class="flex max-h-72 flex-col gap-3 overflow-y-auto bg-white p-4">
                    <p v-if="!ticket.messages.length" class="text-center text-sm text-slate-500">Nenhuma mensagem neste chamado.</p>
                    <div
                        v-for="message in ticket.messages"
                        :key="message.id"
                        :class="['flex', message.sender_type === 'client' ? 'justify-start' : 'justify-end']"
                    >
                        <div
                            :class="[
                                'max-w-[85%] rounded-2xl px-3 py-2 text-sm shadow-sm',
                                message.sender_type === 'client'
                                    ? 'rounded-bl-sm border border-slate-200 bg-slate-50 text-slate-900'
                                    : message.visible_to_client
                                        ? 'rounded-br-sm bg-blue-100 text-blue-950'
                                        : 'rounded-br-sm bg-amber-50 text-amber-950 ring-1 ring-amber-200',
                            ]"
                        >
                            <p class="mb-1 text-xs font-semibold">
                                {{ message.sender_name }}
                                <span v-if="!message.visible_to_client" class="text-amber-700">· Nota interna</span>
                            </p>
                            <p v-if="message.body" class="whitespace-pre-wrap leading-6">{{ message.body }}</p>
                            <div v-if="message.attachments?.length" class="mt-2 grid gap-1">
                                <a
                                    v-for="attachment in message.attachments"
                                    :key="attachment.id"
                                    :href="attachment.download_url"
                                    class="inline-flex items-center gap-2 rounded-lg bg-white/60 px-2 py-1 text-xs font-medium underline-offset-2 hover:underline"
                                    :class="message.visible_to_client ? 'text-blue-950' : 'text-amber-950'"
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

            <form v-if="canUpdate" class="grid gap-3" @submit.prevent="submitReply">
                <div class="flex flex-wrap gap-4">
                    <CheckboxInput v-model="internalNote" label="Nota interna (não visível ao cliente)" />
                    <CheckboxInput v-if="!internalNote" v-model="replyVisibleToClient" label="Visível ao cliente" />
                </div>
                <TextareaInput id="ticket-reply-body" v-model="replyForm.body" label="Resposta" :error="replyForm.errors.body" />
                <label class="grid gap-2">
                    <span class="text-sm font-medium text-slate-900">Anexo (opcional)</span>
                    <input
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                        class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold"
                        @change="onAttachmentChange"
                    />
                    <span v-if="replyForm.errors.attachment" class="text-xs font-medium text-red-600">{{ replyForm.errors.attachment }}</span>
                </label>
                <div class="flex justify-end">
                    <Button type="submit" :loading="replyForm.processing" :disabled="!replyForm.body.trim() && !replyForm.attachment">{{ internalNote ? 'Registrar nota' : 'Enviar resposta' }}</Button>
                </div>
            </form>

            <section v-if="ticket.rating" class="grid gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
                <div>
                    <h3 class="text-sm font-semibold text-amber-950">Avaliação do cliente</h3>
                    <p class="mt-1 text-xs text-amber-800">{{ ticket.rating.rated_by }} · {{ ticket.rating.rated_at }}</p>
                </div>
                <StarRating :model-value="ticket.rating.score" readonly size="sm" />
                <p v-if="ticket.rating.comment" class="text-sm text-amber-950">{{ ticket.rating.comment }}</p>
            </section>
        </div>

        <template #actions>
            <Button variant="ghost" @click="closeDrawer">Fechar</Button>
        </template>
    </Modal>
</template>
