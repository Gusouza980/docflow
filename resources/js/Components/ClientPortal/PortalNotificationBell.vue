<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import IconButton from '../UI/IconButton.vue';

const page = usePage();
const open = ref(false);
const loading = ref(false);
const notifications = ref([]);
const unreadCount = ref(page.props.portal?.notifications?.unread_count ?? 0);

const hasUnread = computed(() => unreadCount.value > 0);

watch(() => page.props.portal?.notifications?.unread_count, (count) => {
    if (typeof count === 'number') {
        unreadCount.value = count;
    }
});

async function fetchNotifications() {
    loading.value = true;

    try {
        const response = await fetch('/client-portal/notifications?unread_only=1&limit=8', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        notifications.value = data.notifications ?? [];
        unreadCount.value = data.unread_count ?? 0;
    } finally {
        loading.value = false;
    }
}

function togglePanel() {
    open.value = !open.value;

    if (open.value) {
        fetchNotifications();
    }
}

function closePanel() {
    open.value = false;
}

function markRead(notification) {
    router.patch(`/client-portal/notifications/${notification.id}/read`, {}, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            unreadCount.value = Math.max(0, unreadCount.value - 1);
            notifications.value = notifications.value.filter((item) => item.id !== notification.id);
            closePanel();

            if (notification.url) {
                router.visit(notification.url);
            }
        },
    });
}

function markAllRead() {
    router.post('/client-portal/notifications/read-all', {}, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            unreadCount.value = 0;
            notifications.value = [];
        },
    });
}

function formatTime(iso) {
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

let pollTimer = null;

onMounted(() => {
    pollTimer = window.setInterval(fetchNotifications, 60000);
});

onUnmounted(() => {
    if (pollTimer) {
        window.clearInterval(pollTimer);
    }
});
</script>

<template>
    <div class="relative">
        <IconButton label="Notificações" @click="togglePanel">
            <span aria-hidden="true">🔔</span>
            <span
                v-if="hasUnread"
                class="absolute -right-1 -top-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"
            >
                {{ unreadCount > 9 ? '9+' : unreadCount }}
            </span>
        </IconButton>

        <div
            v-if="open"
            class="absolute right-0 z-30 mt-2 w-[22rem] overflow-hidden rounded-xl border border-emerald-100 bg-white shadow-xl"
        >
            <div class="flex items-center justify-between border-b border-emerald-50 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-950">Notificações</h2>
                <button
                    v-if="hasUnread"
                    type="button"
                    class="text-xs font-semibold text-emerald-700 hover:text-emerald-800"
                    @click="markAllRead"
                >
                    Marcar todas como lidas
                </button>
            </div>

            <div v-if="loading" class="px-4 py-6 text-sm text-slate-500">Carregando...</div>
            <div v-else-if="!notifications.length" class="px-4 py-6 text-sm text-slate-500">
                Nenhuma notificação pendente.
            </div>
            <ul v-else class="max-h-96 overflow-y-auto">
                <li v-for="notification in notifications" :key="notification.id" class="border-b border-emerald-50 last:border-0">
                    <button
                        type="button"
                        class="w-full px-4 py-3 text-left transition hover:bg-emerald-50"
                        @click="markRead(notification)"
                    >
                        <p class="text-sm font-semibold text-slate-950">{{ notification.title }}</p>
                        <p class="mt-1 text-sm leading-5 text-slate-600">{{ notification.body }}</p>
                        <p class="mt-2 text-xs text-slate-400">{{ formatTime(notification.created_at) }}</p>
                    </button>
                </li>
            </ul>

            <div class="border-t border-emerald-50 px-4 py-3">
                <Link href="/client-portal/notifications" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800" @click="closePanel">
                    Ver todas
                </Link>
            </div>
        </div>
    </div>
</template>
