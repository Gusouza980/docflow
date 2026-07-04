<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import ClientPortalLayout from '../../../Layouts/ClientPortalLayout.vue';
import Button from '../../../Components/UI/Button.vue';
import Card from '../../../Components/UI/Card.vue';

defineProps({
    notifications: { type: Array, default: () => [] },
    unread_count: { type: Number, default: 0 },
});
const page = usePage();

function markRead(notification) {
    router.patch(`/client-portal/notifications/${notification.id}/read`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (notification.url) {
                router.visit(notification.url);
            }
        },
    });
}

function markAllRead() {
    router.post('/client-portal/notifications/read-all', { preserveScroll: true });
}

function formatTime(iso) {
    if (!iso) {
        return '';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}
</script>

<template>
    <Head title="Notificações" />
    <ClientPortalLayout title="Notificações" active-nav="notifications">
        <Card title="Centro de notificações" subtitle="Alertas do escritório sobre chamados, documentos e mensagens.">
            <div class="mb-4 flex items-center justify-between gap-3">
                <p class="text-sm text-slate-600">{{ unread_count }} não lida(s)</p>
                <Button v-if="unread_count" size="sm" variant="secondary" @click="markAllRead">Marcar todas como lidas</Button>
            </div>

            <ul v-if="notifications.length" class="divide-y divide-emerald-50">
                <li v-for="notification in notifications" :key="notification.id" class="py-4 first:pt-0">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-950">{{ notification.title }}</p>
                            <p class="mt-1 text-sm leading-6 text-slate-600">{{ notification.body }}</p>
                            <p class="mt-2 text-xs text-slate-400">{{ formatTime(notification.created_at) }}</p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <Button v-if="!notification.is_read" size="sm" variant="secondary" @click="markRead(notification)">Marcar lida</Button>
                            <Link v-if="notification.url" :href="notification.url" class="inline-flex items-center text-sm font-semibold text-emerald-700 hover:text-emerald-800">
                                Abrir
                            </Link>
                        </div>
                    </div>
                </li>
            </ul>
            <p v-else class="text-sm text-slate-500">Nenhuma notificação registrada.</p>
        </Card>
    </ClientPortalLayout>
</template>
