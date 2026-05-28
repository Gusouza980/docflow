<script setup>
defineProps({
    className: { type: String, default: 'h-4 w-full' },
});
</script>

<template>
    <div :class="['animate-pulse rounded bg-slate-200', className]" aria-hidden="true" />
</template>
