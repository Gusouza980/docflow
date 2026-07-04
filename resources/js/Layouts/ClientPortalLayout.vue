<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Alert from '../Components/Feedback/Alert.vue';
import PortalNotificationBell from '../Components/ClientPortal/PortalNotificationBell.vue';
import Button from '../Components/UI/Button.vue';

defineProps({
    title: { type: String, default: 'Portal do cliente' },
    activeNav: { type: String, default: 'home' },
});

const page = usePage();
const logoutForm = useForm({});

const portal = computed(() => page.props.portal ?? null);
const nav = computed(() => portal.value?.nav ?? {});

const navItems = [
    { key: 'home', label: 'Início', href: '/client-portal', icon: 'home' },
    { key: 'documents', label: 'Documentos', href: '/client-portal/documents', icon: 'documents', badge: () => nav.value.pendingDocuments },
    { key: 'tickets', label: 'Chamados', href: '/client-portal/tickets', icon: 'tickets', badge: () => (nav.value.unreadTicketReplies ?? 0) + (nav.value.ticketsAwaitingResponse ?? 0) + (nav.value.ticketsAwaitingRating ?? 0) },
    { key: 'messages', label: 'Mensagens', href: '/client-portal/messages', icon: 'messages', badge: () => nav.value.unreadMessages },
    { key: 'more', label: 'Mais', href: '/client-portal/more', icon: 'more', badge: () => nav.value.pendingMeetings },
];

function logout() {
    logoutForm.post('/client-portal/logout');
}
</script>

<template>
    <div class="min-h-screen bg-gradient-to-b from-emerald-50 via-slate-50 to-slate-100 text-slate-900">
        <header class="border-b border-emerald-100/80 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <div class="min-w-0">
                    <p class="truncate text-xs font-medium uppercase tracking-wide text-emerald-700">{{ portal?.client?.organization?.name }}</p>
                    <h1 class="truncate text-lg font-semibold text-slate-950 sm:text-xl">{{ title }}</h1>
                    <p class="truncate text-sm text-slate-500">{{ portal?.client?.contact?.name }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <PortalNotificationBell />
                    <Button variant="secondary" size="sm" class="hidden sm:inline-flex" :loading="logoutForm.processing" @click="logout">Sair</Button>
                </div>
            </div>
        </header>

        <div class="mx-auto grid max-w-6xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[15rem_1fr] lg:py-8">
            <nav class="hidden lg:block">
                <div class="sticky top-6 overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-sm shadow-emerald-100/40">
                    <div class="border-b border-emerald-50 px-4 py-4">
                        <p class="text-sm font-semibold text-slate-950">{{ portal?.client?.name }}</p>
                        <p class="text-xs text-slate-500">Área do cliente</p>
                    </div>
                    <ul class="space-y-1 p-2">
                        <li v-for="item in navItems" :key="item.key">
                            <Link
                                :href="item.href"
                                :class="[
                                    'flex items-center justify-between rounded-xl px-3 py-2.5 text-sm font-medium transition',
                                    activeNav === item.key
                                        ? 'bg-emerald-600 text-white shadow-sm'
                                        : 'text-slate-700 hover:bg-emerald-50 hover:text-emerald-900',
                                ]"
                            >
                                <span>{{ item.label }}</span>
                                <span
                                    v-if="item.badge?.()"
                                    :class="[
                                        'rounded-full px-2 py-0.5 text-xs font-semibold',
                                        activeNav === item.key ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800',
                                    ]"
                                >
                                    {{ item.badge() }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                    <div class="border-t border-emerald-50 p-2">
                        <button
                            type="button"
                            class="w-full rounded-xl px-3 py-2.5 text-left text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                            @click="logout"
                        >
                            Sair da conta
                        </button>
                    </div>
                </div>
            </nav>

            <div class="min-w-0 pb-20 lg:pb-0">
                <div v-if="page.props.flash?.status || page.props.flash?.error" class="mb-4 grid gap-3">
                    <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
                    <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>
                </div>
                <slot />
            </div>
        </div>

        <nav class="fixed inset-x-0 bottom-0 z-20 border-t border-emerald-100 bg-white/95 backdrop-blur lg:hidden">
            <ul class="mx-auto grid max-w-lg grid-cols-5">
                <li v-for="item in navItems" :key="item.key">
                    <Link
                        :href="item.href"
                        :class="[
                            'relative flex flex-col items-center gap-1 px-2 py-3 text-[11px] font-medium transition',
                            activeNav === item.key ? 'text-emerald-700' : 'text-slate-500',
                        ]"
                    >
                        <span
                            :class="[
                                'flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold',
                                activeNav === item.key ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600',
                            ]"
                        >
                            {{ item.label.charAt(0) }}
                        </span>
                        {{ item.label }}
                        <span
                            v-if="item.badge?.()"
                            class="absolute right-3 top-2 rounded-full bg-amber-500 px-1.5 text-[10px] font-bold text-white"
                        >
                            {{ item.badge() }}
                        </span>
                    </Link>
                </li>
            </ul>
        </nav>
    </div>
</template>
