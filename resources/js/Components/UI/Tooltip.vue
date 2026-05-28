<script setup>
defineProps({
    text: { type: String, required: true },
});
</script>

<template>
    <span class="group relative inline-flex">
        <slot />
        <span
            role="tooltip"
            class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white shadow-sm group-hover:block group-focus-within:block"
        >
            {{ text }}
        </span>
    </span>
</template>
