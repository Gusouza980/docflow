<script setup>
defineProps({
    eyebrow: { type: String, required: true },
    title: { type: String, required: true },
    description: { type: String, required: true },
});
</script>

<template>
    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-5 max-w-3xl">
            <span class="text-xs font-semibold uppercase tracking-normal text-blue-700">{{ eyebrow }}</span>
            <h2 class="mt-1 text-xl font-bold text-slate-950">{{ title }}</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">{{ description }}</p>
        </div>
        <slot />
    </section>
</template>
