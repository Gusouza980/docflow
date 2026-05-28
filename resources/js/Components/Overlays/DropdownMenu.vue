<script setup>
import { ref } from 'vue';

defineProps({
    label: { type: String, default: 'Ações' },
    items: { type: Array, default: () => [] },
});

const open = ref(false);
</script>

<template>
    <div class="relative inline-block text-left">
        <button
            type="button"
            class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-800 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2"
            :aria-expanded="open"
            @click="open = !open"
        >
            {{ label }}
        </button>
        <div v-if="open" class="absolute right-0 z-30 mt-2 w-56 rounded-md border border-slate-200 bg-white p-1 shadow-md">
            <button
                v-for="item in items"
                :key="item.label"
                type="button"
                :class="['block w-full rounded px-3 py-2 text-left text-sm font-medium hover:bg-slate-50', item.danger ? 'text-red-700' : 'text-slate-700']"
                @click="open = false"
            >
                {{ item.label }}
            </button>
        </div>
    </div>
</template>
