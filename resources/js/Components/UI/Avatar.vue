<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
    size: { type: String, default: 'md' },
});

const sizes = {
    sm: 'h-6 w-6 text-[11px]',
    md: 'h-8 w-8 text-xs',
    lg: 'h-10 w-10 text-sm',
};

const initials = computed(() => props.name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase());
</script>

<template>
    <span :class="['inline-flex shrink-0 items-center justify-center rounded-full bg-blue-50 font-semibold text-blue-700 ring-1 ring-blue-100', sizes[size] ?? sizes.md]">
        {{ initials }}
    </span>
</template>
