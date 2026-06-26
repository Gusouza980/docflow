<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Button from '../../Components/UI/Button.vue';
import TextInput from '../../Components/Forms/TextInput.vue';

const page = usePage();
const form = useForm({ email: '' });

function submit() {
    form.post('/portal/forgot-password', { preserveScroll: true });
}
</script>

<template>
    <Head title="Recuperar senha do portal" />
    <AuthLayout title="Recuperar senha" subtitle="Informe o e-mail do portal. Enviaremos instruções se houver acesso ativo.">
        <Alert v-if="page.props.flash?.status" tone="success" class="mb-5">{{ page.props.flash.status }}</Alert>

        <form class="grid gap-4" @submit.prevent="submit">
            <TextInput id="portal-forgot-email" v-model="form.email" type="email" label="E-mail" required :error="form.errors.email" />
            <Button type="submit" :loading="form.processing">Enviar instruções</Button>
            <Link href="/portal/login" class="text-center text-sm font-semibold text-blue-700 hover:text-blue-800">Voltar para o login</Link>
        </form>
    </AuthLayout>
</template>
