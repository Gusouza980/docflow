<script setup>
import Button from '../UI/Button.vue';

defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    description: { type: String, default: null },
});

defineEmits(['close']);
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/40 p-4" role="dialog" aria-modal="true">
        <div class="flex max-h-[calc(100vh-2rem)] w-full max-w-lg flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-md">
            <div class="shrink-0 border-b border-slate-200 p-5">
                <h2 class="text-lg font-semibold text-slate-950">{{ title }}</h2>
                <p v-if="description" class="mt-2 text-sm leading-6 text-slate-600">{{ description }}</p>
            </div>
            <div class="min-h-0 overflow-y-auto p-5">
                <slot />
            </div>
            <div class="flex shrink-0 justify-end gap-3 border-t border-slate-200 p-4">
                <Button variant="ghost" @click="$emit('close')">Voltar</Button>
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
