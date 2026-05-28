<script setup>
import FieldWrapper from './FieldWrapper.vue';

defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: String, default: '' },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <FieldWrapper :id="id" :label="label">
        <div class="flex min-h-10 overflow-hidden rounded-lg border border-slate-300 bg-white shadow-sm focus-within:border-blue-600 focus-within:ring-2 focus-within:ring-blue-300">
            <span class="inline-flex items-center border-r border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-500">R$</span>
            <input
                :id="id"
                type="text"
                inputmode="decimal"
                :value="modelValue"
                class="min-w-0 flex-1 border-0 px-3 py-2 text-sm text-slate-900 outline-none"
                @input="$emit('update:modelValue', $event.target.value)"
            />
        </div>
    </FieldWrapper>
</template>
