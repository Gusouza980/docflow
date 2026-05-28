<script setup>
defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    hint: { type: String, default: null },
    error: { type: String, default: null },
    required: { type: Boolean, default: false },
});
</script>

<template>
    <label :for="id" class="grid gap-1.5 text-sm font-semibold text-slate-700">
        <span>
            {{ label }}
            <span v-if="required" class="text-red-600" aria-label="obrigatório">*</span>
        </span>
        <slot :described-by="error ? `${id}-error` : hint ? `${id}-hint` : undefined" />
        <span v-if="error" :id="`${id}-error`" class="text-xs font-medium text-red-700">{{ error }}</span>
        <span v-else-if="hint" :id="`${id}-hint`" class="text-xs font-normal text-slate-500">{{ hint }}</span>
    </label>
</template>
