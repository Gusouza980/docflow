<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppTopbar from '../Components/Layout/AppTopbar.vue';
import ImpersonationBanner from '../Components/Layout/ImpersonationBanner.vue';
import MainSidebar from '../Components/Layout/MainSidebar.vue';
import PlanUsageBanner from '../Components/Layout/PlanUsageBanner.vue';
import SubscriptionBanner from '../Components/Layout/SubscriptionBanner.vue';

defineProps({
    title: { type: String, default: 'Docflow' },
    breadcrumbs: { type: Array, default: () => [] },
    activeNav: { type: String, default: null },
});

const page = usePage();

const organizationName = computed(() => page.props.auth?.membership?.organization?.name ?? 'Sem organização');
const user = computed(() => page.props.auth?.user ?? { name: 'Usuário', email: '' });
const mobileNav = computed(() => [
    { key: 'my-day', label: 'Meu dia', href: '/plataforma/my-day' },
    { key: 'clients', label: 'Clientes', href: '/plataforma/clients' },
    { key: 'tasks', label: 'Tarefas', href: '/plataforma/tasks' },
    ...(page.props.auth?.permissions?.can_access_finance ? [
        { key: 'finance', label: 'Financeiro', href: '/plataforma/finance' },
    ] : []),
]);
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <ImpersonationBanner />
        <div class="grid min-h-screen lg:grid-cols-[264px_1fr]">
            <aside class="hidden lg:block">
                <MainSidebar :active="activeNav" />
            </aside>
            <div class="min-w-0">
                <AppTopbar :title="title" :breadcrumbs="breadcrumbs" :organization="organizationName" :user="user" />
                <main class="p-4 pb-24 sm:p-6 sm:pb-24 lg:p-8 lg:pb-8">
                    <SubscriptionBanner />
                    <PlanUsageBanner />
                    <slot />
                </main>
                <footer class="border-t border-slate-200 bg-white px-4 py-3 pb-24 text-xs text-slate-500 sm:px-6 lg:px-8 lg:pb-3">
                    Docflow · Plataforma de gestão para escritórios
                </footer>
            </div>
        </div>
        <nav class="fixed inset-x-0 bottom-0 z-20 grid border-t border-slate-200 bg-white lg:hidden" :class="mobileNav.length === 4 ? 'grid-cols-4' : 'grid-cols-3'" aria-label="Navegação rápida">
            <Link
                v-for="item in mobileNav"
                :key="item.key"
                :href="item.href"
                class="flex min-h-14 items-center justify-center px-2 text-xs font-semibold text-slate-600"
                :aria-current="activeNav === item.key ? 'page' : undefined"
            >
                {{ item.label }}
            </Link>
        </nav>
    </div>
</template>
