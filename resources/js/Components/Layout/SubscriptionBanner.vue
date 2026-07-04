<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Alert from '../Feedback/Alert.vue';

const page = usePage();

const summary = computed(() => page.props.auth?.subscription_summary ?? null);

const showTrialWarning = computed(() => {
    if (!summary.value?.is_accessible) {
        return false;
    }

    return summary.value.status === 'trialing'
        && summary.value.trial_days_left !== null
        && summary.value.trial_days_left <= 7;
});

const showPastDueWarning = computed(() => summary.value?.status === 'past_due' && summary.value.on_grace_period);
</script>

<template>
    <Alert v-if="showTrialWarning" tone="warning" class="mb-4">
        <p class="font-medium">
            Seu trial expira em {{ summary.trial_days_left }} {{ summary.trial_days_left === 1 ? 'dia' : 'dias' }}
        </p>
        <p class="mt-1 text-sm">Regularize a assinatura para evitar interrupção do acesso.</p>
        <Link href="/organizations/plan" class="mt-2 inline-block text-sm font-semibold underline">Ver plano e assinatura</Link>
    </Alert>

    <Alert v-else-if="showPastDueWarning" tone="warning" class="mb-4">
        <p class="font-medium">Assinatura inadimplente — prazo de tolerância ativo</p>
        <p class="mt-1 text-sm">Regularize o pagamento para manter o acesso após o período de tolerância.</p>
        <Link href="/organizations/plan" class="mt-2 inline-block text-sm font-semibold underline">Ver plano e assinatura</Link>
    </Alert>
</template>
