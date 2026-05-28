<script setup>
defineProps({
    title: { type: String, default: null },
    subtitle: { type: String, default: null },
});
</script>

<template>
    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div v-if="title || subtitle || $slots.actions" class="flex flex-col gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 v-if="title" class="text-base font-semibold text-slate-950">{{ title }}</h3>
                <p v-if="subtitle" class="mt-1 text-sm leading-6 text-slate-600">{{ subtitle }}</p>
            </div>
            <slot name="actions" />
        </div>
        <div class="p-5">
            <slot />
        </div>
    </section>
</template>
