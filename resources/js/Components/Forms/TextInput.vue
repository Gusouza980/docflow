<script setup>
import FieldWrapper from './FieldWrapper.vue';

defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    modelValue: { type: [String, Number], default: '' },
    placeholder: { type: String, default: '' },
    hint: { type: String, default: null },
    error: { type: String, default: null },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <FieldWrapper :id="id" :label="label" :hint="hint" :error="error" :required="required" v-slot="{ describedBy }">
        <input
            :id="id"
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :aria-describedby="describedBy"
            :aria-invalid="Boolean(error)"
            :disabled="disabled"
            :readonly="readonly"
            class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-300 disabled:bg-slate-100 disabled:text-slate-400"
            @input="$emit('update:modelValue', $event.target.value)"
        />
    </FieldWrapper>
</template>
