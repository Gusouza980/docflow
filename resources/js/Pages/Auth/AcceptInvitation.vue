<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Badge from '../../Components/UI/Badge.vue';
import Button from '../../Components/UI/Button.vue';

const props = defineProps({
    invitation: { type: Object, required: true },
});

const page = usePage();
const form = useForm({});

function submit() {
    form.post(`/invitations/${props.invitation.token}/accept`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Aceitar convite" />
    <AuthLayout title="Aceitar convite" subtitle="Confirme sua participação na organização para acessar o ambiente de trabalho.">
        <Alert v-if="page.props.flash?.status" tone="success" class="mb-5">{{ page.props.flash.status }}</Alert>
        <Alert v-if="!invitation.can_accept" tone="danger" class="mb-5">Este convite não está mais disponível.</Alert>

        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <p class="text-sm text-slate-500">Organização</p>
            <p class="mt-1 font-semibold text-slate-950">{{ invitation.organization?.name }}</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <Badge tone="primary">{{ invitation.email }}</Badge>
                <Badge tone="secondary">{{ invitation.role }}</Badge>
            </div>
        </div>

        <form class="mt-5 grid gap-4" @submit.prevent="submit">
            <Button v-if="page.props.auth?.user" type="submit" :disabled="!invitation.can_accept || form.processing" :loading="form.processing">
                Aceitar convite
            </Button>
            <Link v-else href="/login" class="inline-flex h-10 items-center justify-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700">
                Entrar para aceitar
            </Link>
        </form>
    </AuthLayout>
</template>
