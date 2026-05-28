<script setup>
import FieldWrapper from './FieldWrapper.vue';

defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: String, default: '' },
    rows: { type: Number, default: 4 },
    hint: { type: String, default: null },
    error: { type: String, default: null },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <FieldWrapper :id="id" :label="label" :hint="hint" :error="error" v-slot="{ describedBy }">
        <textarea
            :id="id"
            :rows="rows"
            :value="modelValue"
            :aria-describedby="describedBy"
            :aria-invalid="Boolean(error)"
            class="min-h-24 resize-y rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-300"
            @input="$emit('update:modelValue', $event.target.value)"
        />
    </FieldWrapper>
</template>
