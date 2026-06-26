<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ClientPortalLayout from '../../Layouts/ClientPortalLayout.vue';
import Badge from '../../Components/UI/Badge.vue';
import Card from '../../Components/UI/Card.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';

defineProps({
    summary: { type: Object, required: true },
});
</script>

<template>
    <Head title="Início · Portal" />
    <ClientPortalLayout title="Início" active-nav="home">
        <div class="grid gap-6">
            <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-700 p-6 text-white shadow-lg shadow-emerald-200">
                <p class="text-sm text-emerald-100">Bem-vindo ao seu portal</p>
                <h2 class="mt-1 text-2xl font-semibold">Tudo o que você precisa, em um só lugar</h2>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-emerald-50">
                    Envie documentos, acompanhe chamados, converse com o escritório e consulte cobranças com segurança.
                </p>
            </section>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Link href="/client-portal/documents" class="group rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md">
                    <p class="text-sm text-slate-500">Documentos pendentes</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-950">{{ summary.pendingDocumentItems }}</p>
                    <p class="mt-2 text-sm text-emerald-700 group-hover:underline">Ver solicitações</p>
                </Link>
                <Link href="/client-portal/tickets" class="group rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md">
                    <p class="text-sm text-slate-500">Chamados abertos</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-950">{{ summary.openTicketsCount }}</p>
                    <p v-if="summary.ticketsAwaitingResponse" class="mt-1 text-xs text-amber-700">{{ summary.ticketsAwaitingResponse }} aguardando resposta</p>
                    <p v-if="summary.ticketsAwaitingRating" class="mt-1 text-xs text-amber-700">{{ summary.ticketsAwaitingRating }} para avaliar</p>
                </Link>
                <Link href="/client-portal/finance" class="group rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md">
                    <p class="text-sm text-slate-500">Cobranças em aberto</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-950">{{ summary.receivablesCount }}</p>
                    <p class="mt-2 text-sm text-emerald-700 group-hover:underline">Consultar cobranças</p>
                </Link>
                <Link href="/client-portal/messages" class="group rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md">
                    <p class="text-sm text-slate-500">Comunicação</p>
                    <p class="mt-2 text-lg font-semibold text-slate-950">Mensagens</p>
                    <p class="mt-2 text-sm text-emerald-700 group-hover:underline">Abrir conversa</p>
                </Link>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <Card title="Documentos recentes">
                    <div class="grid gap-3">
                        <Link
                            v-for="request in summary.recentDocumentRequests"
                            :key="request.id"
                            :href="`/client-portal/documents/${request.id}`"
                            class="rounded-xl border border-slate-200 p-4 transition hover:border-emerald-200 hover:bg-emerald-50/40"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-950">{{ request.title }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ request.received_items_count }} de {{ request.items_count }} itens enviados</p>
                                </div>
                                <StatusPill :status="request.status" />
                            </div>
                        </Link>
                        <p v-if="!summary.recentDocumentRequests.length" class="text-sm text-slate-500">Nenhuma solicitação documental.</p>
                    </div>
                    <template #actions><Link href="/client-portal/documents" class="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-[13px] font-semibold text-slate-800 hover:bg-slate-50">Ver todos</Link></template>
                </Card>

                <Card title="Chamados recentes">
                    <div class="grid gap-3">
                        <Link
                            v-for="ticket in summary.recentTickets"
                            :key="ticket.id"
                            :href="`/client-portal/tickets?ticket=${ticket.id}`"
                            class="rounded-xl border border-slate-200 p-4 transition hover:border-emerald-200 hover:bg-emerald-50/40"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-slate-950">{{ ticket.title }}</p>
                                <StatusPill :status="ticket.status" />
                                <Badge v-if="ticket.can_rate" tone="warning">Avaliar</Badge>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Atualizado em {{ ticket.updated_at }}</p>
                        </Link>
                        <p v-if="!summary.recentTickets.length" class="text-sm text-slate-500">Nenhum chamado registrado.</p>
                    </div>
                    <template #actions><Link href="/client-portal/tickets" class="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-[13px] font-semibold text-slate-800 hover:bg-slate-50">Ver todos</Link></template>
                </Card>
            </div>
        </div>
    </ClientPortalLayout>
</template>
