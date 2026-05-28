<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    items: { type: Array, default: () => [] },
    active: { type: String, default: null },
    collapsed: { type: Boolean, default: false },
});
</script>

<template>
    <nav class="h-full border-r border-slate-200 bg-white p-3" aria-label="Navegação principal">
        <div class="mb-5 px-3 py-2 font-bold text-slate-950">{{ collapsed ? 'D' : 'Docflow' }}</div>
        <Link
            v-for="item in items"
            :key="item.key"
            :href="item.href ?? '#'"
            prefetch
            :aria-current="active === item.key ? 'page' : undefined"
            :class="[
                'mb-1 flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-blue-300',
                active === item.key ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
            ]"
        >
            <span class="inline-flex h-5 w-5 items-center justify-center" aria-hidden="true">{{ item.icon ?? '·' }}</span>
            <span v-if="!collapsed">{{ item.label }}</span>
        </Link>
    </nav>
</template>
