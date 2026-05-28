<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import Alert from '../../Components/Feedback/Alert.vue';
import DataTable from '../../Components/Data/DataTable.vue';
import Modal from '../../Components/Overlays/Modal.vue';
import Button from '../../Components/UI/Button.vue';
import Card from '../../Components/UI/Card.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import TextareaInput from '../../Components/Forms/TextareaInput.vue';

const props = defineProps({
    token: { type: String, required: true },
    client: { type: Object, required: true },
    documentRequests: { type: Array, default: () => [] },
    receivables: { type: Array, default: () => [] },
    tickets: { type: Array, default: () => [] },
    announcements: { type: Array, default: () => [] },
    reports: { type: Array, default: () => [] },
});

const page = usePage();
const messageModalOpen = ref(false);
const ticketModalOpen = ref(false);
const money = (cents) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format((cents ?? 0) / 100);
const requestColumns = [{ key: 'title', label: 'Solicitação' }, { key: 'status', label: 'Status' }, { key: 'due_at', label: 'Prazo' }];
const receivableColumns = [{ key: 'description', label: 'Cobrança' }, { key: 'status', label: 'Status' }, { key: 'amount', label: 'Valor' }];
const ticketColumns = [{ key: 'title', label: 'Solicitação' }, { key: 'status', label: 'Status' }];
const messageForm = useForm({ body: '' });
const ticketForm = useForm({ title: '', description: '' });

function submitMessage() {
    messageForm.post(`/client-portal/${props.token}/messages`, { preserveScroll: true, onSuccess: () => messageModalOpen.value = false });
}

function submitTicket() {
    ticketForm.post(`/client-portal/${props.token}/tickets`, { preserveScroll: true, onSuccess: () => ticketModalOpen.value = false });
}
</script>

<template>
    <Head title="Portal do cliente" />
    <main class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white px-4 py-5 sm:px-6 lg:px-8">
            <div class="mx-auto flex max-w-6xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div><p class="text-sm text-slate-500">Portal do cliente</p><h1 class="text-2xl font-semibold text-slate-950">{{ client.name }}</h1></div>
                <div class="flex gap-2"><Button variant="secondary" @click="messageModalOpen = true">Enviar mensagem</Button><Button @click="ticketModalOpen = true">Abrir solicitação</Button></div>
            </div>
        </header>
        <section class="mx-auto grid max-w-6xl gap-4 p-4 sm:p-6 lg:p-8">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <div class="grid gap-4 md:grid-cols-4">
                <Card title="Solicitações"><p class="text-2xl font-semibold text-slate-950">{{ documentRequests.length }}</p></Card>
                <Card title="Cobranças"><p class="text-2xl font-semibold text-slate-950">{{ receivables.length }}</p></Card>
                <Card title="Chamados"><p class="text-2xl font-semibold text-slate-950">{{ tickets.length }}</p></Card>
                <Card title="Relatórios"><p class="text-2xl font-semibold text-slate-950">{{ reports.length }}</p></Card>
            </div>
            <div class="grid gap-4 xl:grid-cols-2">
                <DataTable :columns="requestColumns" :rows="documentRequests" empty-title="Nenhuma solicitação documental">
                    <template #cell-title="{ row }"><div><p class="font-semibold text-slate-950">{{ row.title }}</p><p class="text-xs text-slate-500">{{ row.received_items_count }} de {{ row.items_count }} itens recebidos</p></div></template>
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

        <Modal v-if="messageModalOpen" open title="Enviar mensagem" @close="messageModalOpen = false">
            <form id="client-message-form" class="grid gap-4" @submit.prevent="submitMessage">
                <TextareaInput id="client-message-body" v-model="messageForm.body" label="Mensagem" required :error="messageForm.errors.body" />
            </form>
            <template #actions><Button type="submit" form="client-message-form" :loading="messageForm.processing">Enviar</Button></template>
        </Modal>
        <Modal v-if="ticketModalOpen" open title="Abrir solicitação" @close="ticketModalOpen = false">
            <form id="client-ticket-form" class="grid gap-4" @submit.prevent="submitTicket">
                <TextInput id="client-ticket-title" v-model="ticketForm.title" label="Título" required :error="ticketForm.errors.title" />
                <TextareaInput id="client-ticket-description" v-model="ticketForm.description" label="Descrição" required :error="ticketForm.errors.description" />
            </form>
            <template #actions><Button type="submit" form="client-ticket-form" :loading="ticketForm.processing">Salvar</Button></template>
        </Modal>
    </main>
</template>
