<script setup>
defineProps({
    tabs: { type: Array, required: true },
    modelValue: { type: String, required: true },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div>
        <div class="flex border-b border-slate-200" role="tablist">
            <button
                v-for="tab in tabs"
                :key="tab.value"
                type="button"
                role="tab"
                :aria-selected="modelValue === tab.value"
                :class="[
                    '-mb-px px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-blue-300',
                    modelValue === tab.value ? 'border-b-2 border-blue-600 text-blue-700' : 'text-slate-500 hover:text-slate-900',
                ]"
                @click="$emit('update:modelValue', tab.value)"
            >
                {{ tab.label }}
            </button>
        </div>
        <div class="pt-4">
            <slot />
        </div>
    </div>
</template>
