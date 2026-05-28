<script setup>
defineProps({
    modelValue: { type: Boolean, default: false },
    label: { type: String, required: true },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <label class="inline-flex min-h-10 items-center gap-2 text-sm font-medium text-slate-700">
        <input
            type="checkbox"
            :checked="modelValue"
            class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-300"
            @change="$emit('update:modelValue', $event.target.checked)"
        />
        {{ label }}
    </label>
</template>
