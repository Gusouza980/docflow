<script setup>
defineProps({
    name: { type: String, required: true },
    token: { type: String, required: true },
    hex: { type: String, required: true },
    description: { type: String, required: true },
});
</script>

<template>
    <article class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="h-20 rounded-md ring-1 ring-inset ring-slate-200" :style="{ backgroundColor: hex }" />
        <div class="mt-4 flex items-start justify-between gap-4">
            <div>
                <h3 class="font-semibold text-slate-950">{{ name }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ description }}</p>
            </div>
            <code class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-600">{{ hex }}</code>
        </div>
        <p class="mt-3 text-xs font-medium text-slate-500">{{ token }}</p>
    </article>
</template>
