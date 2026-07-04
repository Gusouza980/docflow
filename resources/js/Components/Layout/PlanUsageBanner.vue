<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Alert from '../Feedback/Alert.vue';

const page = usePage();

const summary = computed(() => page.props.auth?.plan_summary ?? null);
const warnings = computed(() => summary.value?.warnings ?? []);
</script>

<template>
    <Alert v-if="summary?.has_warnings" tone="warning" class="mb-4">
        <p class="font-medium">Uso do plano próximo do limite</p>
        <p class="mt-1 text-sm">{{ warnings.join(' · ') }}</p>
        <Link href="/organizations/plan" class="mt-2 inline-block text-sm font-semibold underline">Ver plano e uso</Link>
    </Alert>
</template>
