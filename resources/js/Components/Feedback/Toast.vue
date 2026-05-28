<script setup>
defineProps({
    tone: { type: String, default: 'success' },
    title: { type: String, required: true },
    message: { type: String, default: null },
});

const tones = {
    success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    info: 'border-blue-200 bg-blue-50 text-blue-900',
    warning: 'border-amber-200 bg-amber-50 text-amber-900',
    danger: 'border-red-200 bg-red-50 text-red-900',
};
</script>

<template>
    <div :class="['w-full max-w-sm rounded-lg border p-4 shadow-md', tones[tone] ?? tones.success]" role="status">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold">{{ title }}</p>
                <p v-if="message" class="mt-1 text-sm opacity-80">{{ message }}</p>
            </div>
            <button type="button" class="rounded px-1 text-lg leading-none opacity-60 hover:opacity-100" aria-label="Fechar toast">×</button>
        </div>
    </div>
</template>
