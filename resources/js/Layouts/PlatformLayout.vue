<script setup>
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    title: { type: String, default: 'Docflow Platform' },
    breadcrumbs: { type: Array, default: () => [] },
    activeNav: { type: String, default: null },
});

const page = usePage();
const user = page.props.auth?.user ?? { name: 'Admin', email: '' };

const items = [
    { key: 'dashboard', label: 'Dashboard', icon: '▦', href: '/platform' },
    { key: 'organizations', label: 'Organizações', icon: '◫', href: '/platform/organizations' },
    { key: 'plans', label: 'Planos', icon: '◈', href: '/platform/plans' },
    { key: 'invoices', label: 'Faturas', icon: '$', href: '/platform/invoices' },
];
</script>

<template>
    <div class="min-h-screen bg-slate-100 text-slate-900">
        <div class="border-b border-slate-800 bg-slate-900 px-4 py-2 text-center text-xs font-medium tracking-wide text-slate-300 sm:text-left">
            Ambiente de administração da plataforma — operações cross-tenant
        </div>
        <div class="grid min-h-[calc(100vh-33px)] lg:grid-cols-[264px_1fr]">
            <aside class="hidden border-r border-slate-800 bg-slate-900 lg:block">
                <nav class="flex h-full flex-col p-3" aria-label="Navegação platform">
                    <div class="mb-5 px-3 py-2">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Docflow</p>
                        <p class="font-bold text-white">Platform</p>
                    </div>
                    <div class="flex-1">
                        <Link
                            v-for="item in items"
                            :key="item.key"
                            :href="item.href"
                            prefetch
                            :aria-current="activeNav === item.key ? 'page' : undefined"
                            :class="[
                                'mb-1 flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-violet-400',
                                activeNav === item.key ? 'bg-violet-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white',
                                item.disabled ? 'pointer-events-none opacity-40' : '',
                            ]"
                        >
                            <span class="inline-flex h-5 w-5 items-center justify-center" aria-hidden="true">{{ item.icon }}</span>
                            <span>{{ item.label }}</span>
                            <span v-if="item.disabled" class="ml-auto text-[10px] uppercase text-slate-500">Em breve</span>
                        </Link>
                    </div>
                    <div class="mt-auto space-y-2 border-t border-slate-700 pt-3">
                        <Link
                            href="/dashboard"
                            class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-slate-300 transition hover:bg-slate-800 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400"
                        >
                            <span aria-hidden="true">←</span>
                            <span>Voltar ao app</span>
                        </Link>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-slate-300 transition hover:bg-slate-800 hover:text-white focus:outline-none focus:ring-2 focus:ring-violet-400"
                        >
                            <span aria-hidden="true">↪</span>
                            <span>Sair</span>
                        </Link>
                    </div>
                </nav>
            </aside>
            <div class="min-w-0">
                <header class="flex min-h-16 flex-col gap-2 border-b border-slate-200 bg-white px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <nav v-if="breadcrumbs.length" class="text-xs text-slate-500">
                            <span v-for="(crumb, index) in breadcrumbs" :key="index">
                                <span v-if="index > 0"> / </span>{{ crumb.label }}
                            </span>
                        </nav>
                        <h1 class="mt-1 text-lg font-semibold text-slate-950">{{ title }}</h1>
                    </div>
                    <div class="text-sm text-slate-600">
                        <span class="font-medium text-slate-900">{{ user.name }}</span>
                        <span class="text-slate-400"> · </span>{{ user.email }}
                    </div>
                </header>
                <main class="p-4 sm:p-6 lg:p-8">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
