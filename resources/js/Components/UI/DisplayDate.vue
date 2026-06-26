<script setup>
import { computed } from 'vue';
import { useDisplayFormat } from '../../composables/useDisplayFormat';

const props = defineProps({
    value: { type: [String, Number, Date], default: null },
    mode: { type: String, default: 'date' },
    fallback: { type: String, default: '—' },
});

const { formatDate, formatDateTime } = useDisplayFormat();

const formatted = computed(() => {
    const result = props.mode === 'datetime'
        ? formatDateTime(props.value)
        : formatDate(props.value);

    return result || props.fallback;
});
</script>

<template>
    <time v-if="formatted" :datetime="typeof value === 'string' ? value : undefined">{{ formatted }}</time>
    <span v-else>{{ fallback }}</span>
</template>
