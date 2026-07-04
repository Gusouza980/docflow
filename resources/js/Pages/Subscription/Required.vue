<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Card from '../../Components/UI/Card.vue';
import Button from '../../Components/UI/Button.vue';

defineProps({
    organization: { type: Object, default: null },
    blockReason: { type: String, default: null },
    blockMessage: { type: String, required: true },
    canManagePlan: { type: Boolean, default: false },
});

const page = usePage();
</script>

<template>
    <Head title="Assinatura necessária" />
    <AppLayout title="Acesso indisponível" active-nav="organizations">
        <div class="mx-auto grid max-w-2xl gap-4">
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <Card title="Acesso temporariamente indisponível">
                <p class="text-sm text-slate-700">{{ blockMessage }}</p>
                <p v-if="organization" class="mt-3 text-sm text-slate-500">Organização: <strong>{{ organization.name }}</strong></p>

                <div class="mt-6 flex flex-wrap gap-3">
                    <Link v-if="canManagePlan" href="/organizations/plan">
                        <Button>Ver plano e assinatura</Button>
                    </Link>
                    <Link href="/organizations">
                        <Button variant="secondary">Trocar organização</Button>
                    </Link>
                </div>

                <p class="mt-6 text-sm text-slate-500">
                    Precisa de ajuda? Entre em contato com o suporte Docflow em
                    <a href="mailto:suporte@docflow.test" class="font-semibold text-blue-700 hover:text-blue-900">suporte@docflow.test</a>.
                </p>
            </Card>
        </div>
    </AppLayout>
</template>
