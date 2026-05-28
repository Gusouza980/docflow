<script setup>
defineProps({
    tone: { type: String, default: 'neutral' },
});

const tones = {
    neutral: 'bg-white text-slate-700 ring-slate-200',
    primary: 'bg-blue-50 text-blue-700 ring-blue-200',
    secondary: 'bg-teal-50 text-teal-700 ring-teal-200',
    warning: 'bg-amber-50 text-amber-700 ring-amber-200',
    danger: 'bg-red-50 text-red-700 ring-red-200',
    success: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
};
</script>

<template>
    <span :class="['inline-flex h-6 items-center rounded-full px-2.5 text-xs font-medium ring-1 ring-inset', tones[tone] ?? tones.neutral]">
        <slot />
    </span>
</template>
