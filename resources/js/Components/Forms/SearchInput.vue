<script setup>
defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Buscar' },
    label: { type: String, default: 'Buscar' },
});

defineEmits(['update:modelValue', 'clear']);
</script>

<template>
    <label class="relative block">
        <span class="sr-only">{{ label }}</span>
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">⌕</span>
        <input
            type="search"
            :value="modelValue"
            :placeholder="placeholder"
            class="h-10 w-full rounded-lg border border-slate-300 bg-white pl-9 pr-10 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-300"
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <button
            v-if="modelValue"
            type="button"
            class="absolute right-2 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded text-slate-500 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-300"
            aria-label="Limpar busca"
            @click="$emit('update:modelValue', ''); $emit('clear')"
        >
            ×
        </button>
    </label>
</template>
