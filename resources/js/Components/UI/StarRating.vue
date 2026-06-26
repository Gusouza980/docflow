<script setup>
import { ref } from 'vue';

const props = defineProps({
    modelValue: { type: Number, default: 0 },
    readonly: { type: Boolean, default: false },
    size: { type: String, default: 'md' },
    label: { type: String, default: null },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const hoverValue = ref(0);

const sizeClasses = {
    sm: 'h-5 w-5',
    md: 'h-8 w-8',
    lg: 'h-10 w-10',
};

function activeValue(index) {
    const current = hoverValue.value || props.modelValue;

    return index <= current;
}

function selectRating(value) {
    if (props.readonly) {
        return;
    }

    emit('update:modelValue', value);
}

function onMouseLeave() {
    hoverValue.value = 0;
}
</script>

<template>
    <div class="grid gap-2">
        <p v-if="label" class="text-sm font-medium text-slate-900">{{ label }}</p>
        <div
            class="flex items-center gap-1"
            role="radiogroup"
            :aria-label="label ?? 'Avaliação em estrelas'"
            @mouseleave="onMouseLeave"
        >
            <button
                v-for="star in 5"
                :key="star"
                type="button"
                :disabled="readonly"
                :class="[
                    'transition focus:outline-none focus:ring-2 focus:ring-amber-300 focus:ring-offset-1',
                    readonly ? 'cursor-default' : 'cursor-pointer hover:scale-110',
                ]"
                :aria-label="`${star} ${star === 1 ? 'estrela' : 'estrelas'}`"
                :aria-checked="modelValue === star"
                role="radio"
                @click="selectRating(star)"
                @mouseenter="readonly ? null : (hoverValue = star)"
            >
                <svg
                    :class="[sizeClasses[size] ?? sizeClasses.md, activeValue(star) ? 'text-amber-400' : 'text-slate-300']"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 22 12 18.56 5.82 22 7 14.14l-5-4.87 6.91-1.01L12 2z" />
                </svg>
            </button>
        </div>
        <p v-if="error" class="text-xs font-medium text-red-600">{{ error }}</p>
    </div>
</template>
