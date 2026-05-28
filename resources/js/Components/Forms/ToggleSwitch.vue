<script setup>
defineProps({
    modelValue: { type: Boolean, default: false },
    label: { type: String, required: true },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <label class="inline-flex min-h-10 items-center gap-3 text-sm font-medium text-slate-700">
        <button
            type="button"
            role="switch"
            :aria-checked="modelValue"
            :class="[
                'relative h-6 w-10 rounded-full transition focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2',
                modelValue ? 'bg-blue-600' : 'bg-slate-300',
            ]"
            @click="$emit('update:modelValue', !modelValue)"
        >
            <span :class="['absolute top-1 h-4 w-4 rounded-full bg-white transition', modelValue ? 'left-5' : 'left-1']" />
        </button>
        {{ label }}
    </label>
</template>
