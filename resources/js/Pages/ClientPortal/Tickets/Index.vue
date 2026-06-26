<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ClientPortalLayout from '../../../Layouts/ClientPortalLayout.vue';
import ClientPortalTicketDrawer from '../../../Components/ClientPortal/ClientPortalTicketDrawer.vue';
import Badge from '../../../Components/UI/Badge.vue';
import Button from '../../../Components/UI/Button.vue';
import Modal from '../../../Components/Overlays/Modal.vue';
import StarRating from '../../../Components/UI/StarRating.vue';
import StatusPill from '../../../Components/UI/StatusPill.vue';
import TextInput from '../../../Components/Forms/TextInput.vue';
import TextareaInput from '../../../Components/Forms/TextareaInput.vue';

const props = defineProps({
    tickets: { type: Array, default: () => [] },
    selectedTicketId: { type: Number, default: null },
});

const ticketModalOpen = ref(false);
const activeTicketId = ref(props.selectedTicketId);
const ticketFilter = ref('all');
const ticketForm = useForm({ title: '', description: '' });

const filteredTickets = computed(() => {
    if (ticketFilter.value === 'all') {
        return props.tickets;
    }

    return props.tickets.filter((ticket) => !ticket.is_closed);
});

function openTicketDrawer(ticketId) {
    activeTicketId.value = ticketId;
    router.get('/client-portal/tickets', { ticket: ticketId }, { preserveState: true, preserveScroll: true, replace: true });
}

function closeTicketDrawer() {
    activeTicketId.value = null;
    router.get('/client-portal/tickets', {}, { preserveState: true, preserveScroll: true, replace: true });
}

watch(() => props.selectedTicketId, (ticketId) => {
    activeTicketId.value = ticketId;
});

function submitTicket() {
    ticketForm.post('/client-portal/tickets', {
        preserveScroll: true,
        onSuccess: () => {
            ticketModalOpen.value = false;
            ticketForm.reset();
        },
    });
}
</script>

<template>
    <Head title="Chamados · Portal" />
    <ClientPortalLayout title="Chamados" active-nav="tickets">
        <div class="grid gap-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-600">Abra solicitações, acompanhe respostas e avalie o atendimento.</p>
                <Button @click="ticketModalOpen = true">Nova solicitação</Button>
            </div>

            <div class="flex gap-2">
                <Button size="sm" :variant="ticketFilter === 'all' ? 'primary' : 'secondary'" @click="ticketFilter = 'all'">Todas</Button>
                <Button size="sm" :variant="ticketFilter === 'open' ? 'primary' : 'secondary'" @click="ticketFilter = 'open'">Abertas</Button>
            </div>

            <div v-if="filteredTickets.length" class="grid gap-3">
                <button
                    v-for="ticket in filteredTickets"
                    :key="ticket.id"
                    type="button"
                    class="rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:border-emerald-200 hover:shadow-md"
                    @click="openTicketDrawer(ticket.id)"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-semibold text-slate-950">{{ ticket.title }}</h2>
                        <StatusPill :status="ticket.status" />
                        <Badge v-if="ticket.needs_response" tone="warning">Aguardando você</Badge>
                        <Badge v-else-if="ticket.has_unread" tone="warning">Nova resposta</Badge>
                        <Badge v-else-if="ticket.can_rate" tone="warning">Avalie o atendimento</Badge>
                        <StarRating v-else-if="ticket.has_rating" :model-value="ticket.rating_score" readonly size="sm" />
                    </div>
                    <p class="mt-2 text-sm text-slate-500">{{ ticket.messages_count }} mensagens · aberta em {{ ticket.created_at }}</p>
                </button>
            </div>

            <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                <p class="text-base font-medium text-slate-900">Nenhum chamado encontrado</p>
                <p class="mt-2 text-sm text-slate-500">Use o botão acima para abrir sua primeira solicitação.</p>
            </div>
        </div>

        <ClientPortalTicketDrawer :open="Boolean(activeTicketId)" :ticket-id="activeTicketId" @close="closeTicketDrawer" />

        <Modal v-if="ticketModalOpen" open title="Nova solicitação" @close="ticketModalOpen = false">
            <form id="client-ticket-form" class="grid gap-4" @submit.prevent="submitTicket">
                <TextInput id="client-ticket-title" v-model="ticketForm.title" label="Título" required :error="ticketForm.errors.title" />
                <TextareaInput id="client-ticket-description" v-model="ticketForm.description" label="Descrição" required :error="ticketForm.errors.description" />
            </form>
            <template #actions><Button type="submit" form="client-ticket-form" :loading="ticketForm.processing">Abrir solicitação</Button></template>
        </Modal>
    </ClientPortalLayout>
</template>
