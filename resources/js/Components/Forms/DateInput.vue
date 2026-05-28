<script setup>
import FieldWrapper from './FieldWrapper.vue';

defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: String, default: '' },
    hint: { type: String, default: 'Formato dd/mm/aaaa' },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <FieldWrapper :id="id" :label="label" :hint="hint" v-slot="{ describedBy }">
        <input
            :id="id"
            type="text"
            inputmode="numeric"
            :value="modelValue"
            :aria-describedby="describedBy"
            class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-300"
            @input="$emit('update:modelValue', $event.target.value)"
        />
    </FieldWrapper>
</template>
