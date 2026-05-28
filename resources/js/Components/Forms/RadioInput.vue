<script setup>
defineProps({
    modelValue: { type: [String, Number], default: null },
    value: { type: [String, Number], required: true },
    name: { type: String, required: true },
    label: { type: String, required: true },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <label class="inline-flex min-h-10 items-center gap-2 text-sm font-medium text-slate-700">
        <input
            type="radio"
            :name="name"
            :value="value"
            :checked="modelValue === value"
            class="h-4 w-4 border-slate-300 text-blue-600 focus:ring-blue-300"
            @change="$emit('update:modelValue', value)"
        />
        {{ label }}
    </label>
</template>
