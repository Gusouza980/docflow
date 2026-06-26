<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Button from '../../Components/UI/Button.vue';

defineProps({
    notifications: { type: Array, default: () => [] },
    unread_count: { type: Number, default: 0 },
});

const page = usePage();

function markRead(notification) {
    router.patch(`/notifications/${notification.id}/read`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (notification.url) {
                router.visit(notification.url);
            }
        },
    });
}

function markAllRead() {
    router.post('/notifications/read-all', {}, { preserveScroll: true });
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
    <AppLayout title="Notificações" active-nav="notifications">
        <div class="mx-auto max-w-3xl grid gap-4">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm text-slate-600">{{ unread_count }} não lida(s)</p>
                <Button v-if="unread_count" size="sm" variant="secondary" @click="markAllRead">Marcar todas como lidas</Button>
            </div>

            <div v-if="page.props.flash?.status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                {{ page.props.flash.status }}
            </div>

            <article
                v-for="notification in notifications"
                :key="notification.id"
                :class="[
                    'rounded-2xl border bg-white p-5 shadow-sm',
                    notification.is_read ? 'border-slate-200 opacity-80' : 'border-blue-200',
                ]"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">{{ notification.title }}</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ notification.body }}</p>
                        <p class="mt-2 text-xs text-slate-400">{{ formatTime(notification.created_at) }}</p>
                    </div>
                    <div class="flex shrink-0 flex-col gap-2">
                        <Link
                            v-if="notification.url"
                            :href="notification.url"
                            class="text-sm font-semibold text-blue-700 hover:text-blue-800"
                        >
                            Abrir
                        </Link>
                        <button
                            v-if="!notification.is_read"
                            type="button"
                            class="text-sm font-semibold text-slate-600 hover:text-slate-800"
                            @click="markRead(notification)"
                        >
                            Marcar lida
                        </button>
                    </div>
                </div>
            </article>

            <p v-if="!notifications.length" class="rounded-2xl border border-dashed border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
                Nenhuma notificação registrada.
            </p>
        </div>
    </AppLayout>
</template>
