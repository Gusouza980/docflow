<script setup>
import FieldWrapper from './FieldWrapper.vue';

defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: String, default: '' },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Buscar e selecionar' },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <FieldWrapper :id="id" :label="label">
        <div class="rounded-lg border border-slate-300 bg-white shadow-sm focus-within:border-blue-600 focus-within:ring-2 focus-within:ring-blue-300">
            <input
                :id="id"
                type="text"
                :value="modelValue"
                :placeholder="placeholder"
                class="h-10 w-full rounded-lg border-0 px-3 text-sm text-slate-900 outline-none placeholder:text-slate-400"
                role="combobox"
                :aria-expanded="true"
                @input="$emit('update:modelValue', $event.target.value)"
            />
            <div class="border-t border-slate-100 p-1">
                <button v-for="option in options" :key="option.value" type="button" class="block w-full rounded px-2 py-1.5 text-left text-sm text-slate-700 hover:bg-slate-50" @click="$emit('update:modelValue', option.label)">
                    {{ option.label }}
                </button>
            </div>
        </div>
    </FieldWrapper>
</template>
