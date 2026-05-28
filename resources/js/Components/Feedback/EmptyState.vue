<script setup>
import Button from '../UI/Button.vue';

defineProps({
    title: { type: String, required: true },
    description: { type: String, required: true },
    actionLabel: { type: String, default: null },
});
</script>

<template>
    <div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-xl font-semibold text-blue-700">+</div>
        <h3 class="mt-4 font-semibold text-slate-950">{{ title }}</h3>
        <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-slate-600">{{ description }}</p>
        <Button v-if="actionLabel" class="mt-5">{{ actionLabel }}</Button>
    </div>
</template>
