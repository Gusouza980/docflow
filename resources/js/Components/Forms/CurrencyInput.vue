<script setup>
import FieldWrapper from './FieldWrapper.vue';
import { formatBrlInput } from '../../lib/money';

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: [String, Number], default: '' },
    hint: { type: String, default: null },
    error: { type: String, default: null },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    placeholder: { type: String, default: '0,00' },
});

const emit = defineEmits(['update:modelValue']);

function onInput(event) {
    emit('update:modelValue', event.target.value);
}

function onBlur(event) {
    const formatted = formatBrlInput(event.target.value);

    if (formatted !== String(props.modelValue ?? '')) {
        emit('update:modelValue', formatted);
    }
}
</script>

<template>
    <FieldWrapper :id="id" :label="label" :hint="hint" :error="error" :required="required" v-slot="{ describedBy }">
        <div class="flex min-h-10 overflow-hidden rounded-lg border border-slate-300 bg-white shadow-sm transition focus-within:border-blue-600 focus-within:ring-2 focus-within:ring-blue-300 has-[:disabled]:bg-slate-100">
            <span class="inline-flex items-center border-r border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-500">R$</span>
            <input
                :id="id"
                type="text"
                inputmode="decimal"
                :value="modelValue"
                :placeholder="placeholder"
                :aria-describedby="describedBy"
                :aria-invalid="Boolean(error)"
                :disabled="disabled"
                class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm text-slate-900 outline-none placeholder:text-slate-400 disabled:text-slate-400"
                @input="onInput"
                @blur="onBlur"
            />
        </div>
    </FieldWrapper>
</template>
