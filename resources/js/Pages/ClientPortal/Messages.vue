<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import ClientPortalLayout from '../../Layouts/ClientPortalLayout.vue';
import Button from '../../Components/UI/Button.vue';

const props = defineProps({
    hasPortalCommunicationConsent: { type: Boolean, default: false },
    messages: { type: Array, default: () => [] },
});

const chatContainer = ref(null);
const chatMessages = ref([...props.messages]);
let pollTimer = null;

const messageForm = useForm({ body: '' });
const consentForm = useForm({});

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
    const lastId = chatMessages.value.at(-1)?.id;
    const url = lastId ? `/client-portal/messages/poll?since_id=${lastId}` : '/client-portal/messages/poll';

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

watch(() => props.messages, (messages) => {
    chatMessages.value = [...messages];
    scrollChatToBottom();
}, { deep: true });

function submitMessage() {
    if (!props.hasPortalCommunicationConsent || !messageForm.body.trim()) {
        return;
    }

    messageForm.post('/client-portal/messages', {
        preserveScroll: true,
        onSuccess: () => {
            messageForm.reset('body');
            pollMessages();
        },
    });
}

function grantPortalCommunicationConsent() {
    consentForm.post('/client-portal/consent', { preserveScroll: true });
}

function openTicketFromMessage(message) {
    router.post(`/client-portal/messages/${message.id}/ticket`, {}, { preserveScroll: true });
}

function handleMessageKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        submitMessage();
    }
}
</script>

<template>
    <Head title="Mensagens · Portal" />
    <ClientPortalLayout title="Mensagens" active-nav="messages">
        <section class="overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-sm">
            <div class="border-b border-emerald-100 bg-emerald-700 px-4 py-4 text-white sm:px-6">
                <h2 class="text-base font-semibold">Conversa com o escritório</h2>
                <p class="text-sm text-emerald-100">Canal seguro de atendimento</p>
            </div>

            <div class="relative">
                <div :class="['transition', !hasPortalCommunicationConsent && 'pointer-events-none select-none blur-sm']">
                    <div ref="chatContainer" class="flex min-h-[24rem] max-h-[calc(100vh-18rem)] flex-col gap-3 overflow-y-auto bg-[#e5ddd5] px-4 py-5 sm:min-h-[28rem]">
                        <p v-if="!chatMessages.length" class="mx-auto max-w-sm rounded-xl bg-white/90 px-4 py-3 text-center text-sm text-slate-600 shadow-sm">
                            Nenhuma mensagem ainda. Envie a primeira mensagem para falar com o escritório.
                        </p>

                        <div
                            v-for="message in chatMessages"
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
                                <p class="mt-1 text-right text-[11px] opacity-70">{{ formatMessageTime(message.created_at) }}</p>
                                <button
                                    v-if="message.can_open_ticket"
                                    type="button"
                                    class="mt-2 text-xs font-semibold text-emerald-800 underline underline-offset-2 hover:text-emerald-950"
                                    @click="openTicketFromMessage(message)"
                                >
                                    Abrir chamado sobre isso
                                </button>
                            </div>
                        </div>
                    </div>

                    <form class="border-t border-slate-200 bg-slate-50 p-3 sm:p-4" @submit.prevent="submitMessage">
                        <div class="flex items-end gap-2">
                            <label class="min-w-0 flex-1">
                                <span class="sr-only">Mensagem</span>
                                <textarea
                                    v-model="messageForm.body"
                                    rows="2"
                                    placeholder="Digite sua mensagem..."
                                    :disabled="!hasPortalCommunicationConsent"
                                    class="max-h-32 min-h-11 w-full resize-none rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm transition placeholder:text-slate-400 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-300 disabled:bg-slate-100"
                                    @keydown="handleMessageKeydown"
                                />
                            </label>
                            <Button type="submit" class="shrink-0" :loading="messageForm.processing" :disabled="!hasPortalCommunicationConsent || !messageForm.body.trim()">Enviar</Button>
                        </div>
                    </form>
                </div>

                <div v-if="!hasPortalCommunicationConsent" class="absolute inset-0 z-10 flex items-center justify-center bg-white/60 p-4 backdrop-blur-[1px]">
                    <div class="max-w-md rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center shadow-lg">
                        <h3 class="text-base font-semibold text-amber-950">Autorize a comunicação</h3>
                        <p class="mt-2 text-sm leading-6 text-amber-900">Para enviar e receber mensagens pelo portal, confirme seu consentimento.</p>
                        <Button class="mt-4" :loading="consentForm.processing" @click="grantPortalCommunicationConsent">Autorizar comunicação</Button>
                    </div>
                </div>
            </div>
        </section>
    </ClientPortalLayout>
</template>
