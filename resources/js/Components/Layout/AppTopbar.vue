<script setup>
import { Link } from '@inertiajs/vue3';
import Avatar from '../UI/Avatar.vue';
import IconButton from '../UI/IconButton.vue';
import SearchInput from '../Forms/SearchInput.vue';
import Breadcrumbs from '../Navigation/Breadcrumbs.vue';

defineProps({
    title: { type: String, required: true },
    breadcrumbs: { type: Array, default: () => [] },
    organization: { type: String, default: 'Docflow Office' },
    user: { type: Object, default: () => ({ name: 'Ana Costa', email: 'ana@example.com' }) },
});
</script>

<template>
    <header class="flex min-h-16 flex-col gap-3 border-b border-slate-200 bg-white px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <Breadcrumbs v-if="breadcrumbs.length" :items="breadcrumbs" />
            <h1 class="mt-1 truncate text-lg font-semibold text-slate-950">{{ title }}</h1>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <SearchInput class="w-full sm:w-64" placeholder="Buscar no sistema" label="Busca global" />
            <Link href="/organizations" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                {{ organization }}
            </Link>
            <IconButton label="Abrir notificações">!</IconButton>
            <div class="flex items-center gap-2">
                <Avatar :name="user.name" />
                <div class="hidden text-sm sm:block">
                    <p class="font-semibold text-slate-900">{{ user.name }}</p>
                    <p class="text-xs text-slate-500">{{ user.email }}</p>
                </div>
            </div>
        </div>
    </header>
</template>
