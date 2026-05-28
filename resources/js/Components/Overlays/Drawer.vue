<script setup>
defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
});

defineEmits(['close']);
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-40 bg-slate-950/40" role="dialog" aria-modal="true">
        <aside class="ml-auto flex h-full w-full max-w-xl flex-col bg-white shadow-md">
            <header class="flex items-center justify-between border-b border-slate-200 p-5">
                <h2 class="text-lg font-semibold text-slate-950">{{ title }}</h2>
                <button type="button" class="rounded px-2 text-2xl text-slate-500 hover:bg-slate-100" aria-label="Fechar drawer" @click="$emit('close')">×</button>
            </header>
            <div class="min-h-0 flex-1 overflow-y-auto p-5">
                <slot />
            </div>
            <footer v-if="$slots.footer" class="border-t border-slate-200 p-4">
                <slot name="footer" />
            </footer>
        </aside>
    </div>
</template>
