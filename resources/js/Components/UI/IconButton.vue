<script setup>
import Tooltip from './Tooltip.vue';

defineProps({
    label: { type: String, required: true },
    size: { type: String, default: 'md' },
    variant: { type: String, default: 'secondary' },
    disabled: { type: Boolean, default: false },
});

const sizeClasses = {
    sm: 'h-8 w-8 text-sm',
    md: 'h-10 w-10 text-base',
};

const variantClasses = {
    secondary: 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
    ghost: 'bg-transparent text-slate-600 hover:bg-slate-100',
    danger: 'border border-red-200 bg-red-50 text-red-700 hover:bg-red-100',
};
</script>

<template>
    <Tooltip :text="label">
        <button
            type="button"
            :aria-label="label"
            :disabled="disabled"
            :class="[
                'inline-flex items-center justify-center rounded-lg font-semibold transition focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60',
                sizeClasses[size] ?? sizeClasses.md,
                variantClasses[variant] ?? variantClasses.secondary,
            ]"
        >
            <slot />
        </button>
    </Tooltip>
</template>
