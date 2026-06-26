<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ClientPortalLayout from '../../../Layouts/ClientPortalLayout.vue';
import StatusPill from '../../../Components/UI/StatusPill.vue';

defineProps({
    documentRequests: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Documentos · Portal" />
    <ClientPortalLayout title="Documentos" active-nav="documents">
        <div class="grid gap-4">
            <p class="text-sm text-slate-600">Solicitações de documentos enviadas pelo escritório. Toque em uma solicitação para enviar arquivos.</p>

            <div v-if="documentRequests.length" class="grid gap-4">
                <Link
                    v-for="request in documentRequests"
                    :key="request.id"
                    :href="`/client-portal/documents/${request.id}`"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-200 hover:shadow-md"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">{{ request.title }}</h2>
                            <p class="mt-1 text-sm text-slate-500">Prazo: {{ request.due_at || 'Sem prazo' }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ request.received_items_count }} de {{ request.items_count }} itens enviados</p>
                        </div>
                        <StatusPill :status="request.status" />
                    </div>
                </Link>
            </div>

            <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                <p class="text-base font-medium text-slate-900">Nenhuma solicitação documental</p>
                <p class="mt-2 text-sm text-slate-500">Quando o escritório solicitar documentos, eles aparecerão aqui.</p>
            </div>
        </div>
    </ClientPortalLayout>
</template>
