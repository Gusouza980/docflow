<script setup>
defineProps({
    tone: { type: String, default: 'info' },
    title: { type: String, default: null },
});

const tones = {
    info: 'bg-blue-50 text-blue-800 ring-blue-200',
    success: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    warning: 'bg-amber-50 text-amber-800 ring-amber-200',
    danger: 'bg-red-50 text-red-800 ring-red-200',
};
</script>

<template>
    <div :class="['rounded-lg p-4 text-sm ring-1 ring-inset', tones[tone] ?? tones.info]" role="status">
        <p v-if="title" class="font-semibold">{{ title }}</p>
        <p :class="title ? 'mt-1' : 'font-medium'"><slot /></p>
    </div>
</template>
