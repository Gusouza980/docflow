<script setup>
defineProps({
    items: { type: Array, required: true },
});
</script>

<template>
    <nav class="flex flex-wrap items-center gap-1 text-sm text-slate-500" aria-label="Breadcrumb">
        <template v-for="(item, index) in items" :key="item.label">
            <a v-if="item.href && index < items.length - 1" :href="item.href" class="font-medium hover:text-blue-700">{{ item.label }}</a>
            <span v-else :class="index === items.length - 1 ? 'font-semibold text-slate-800' : 'font-medium'">{{ item.label }}</span>
            <span v-if="index < items.length - 1" aria-hidden="true">/</span>
        </template>
    </nav>
</template>
