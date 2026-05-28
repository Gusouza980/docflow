<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppTopbar from '../Components/Layout/AppTopbar.vue';
import MainSidebar from '../Components/Layout/MainSidebar.vue';

defineProps({
    title: { type: String, default: 'Docflow' },
    breadcrumbs: { type: Array, default: () => [] },
    activeNav: { type: String, default: null },
});

const page = usePage();

const organizationName = computed(() => page.props.auth?.membership?.organization?.name ?? 'Sem organização');
const user = computed(() => page.props.auth?.user ?? { name: 'Usuário', email: '' });
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <div class="grid min-h-screen lg:grid-cols-[264px_1fr]">
            <aside class="hidden lg:block">
                <MainSidebar :active="activeNav" />
            </aside>
            <div class="min-w-0">
                <AppTopbar :title="title" :breadcrumbs="breadcrumbs" :organization="organizationName" :user="user" />
                <main class="p-4 sm:p-6 lg:p-8">
                    <slot />
                </main>
                <footer class="border-t border-slate-200 bg-white px-4 py-3 text-xs text-slate-500 sm:px-6 lg:px-8">
                    Docflow · Plataforma de gestão para escritórios
                </footer>
            </div>
        </div>
    </div>
</template>
