<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Button from '../../Components/UI/Button.vue';
import CheckboxInput from '../../Components/Forms/CheckboxInput.vue';
import TextInput from '../../Components/Forms/TextInput.vue';

const page = usePage();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/portal/login', {
        preserveScroll: true,
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Portal do cliente" />
    <AuthLayout title="Portal do cliente" subtitle="Entre com seu e-mail e senha para acessar solicitações, documentos e comunicação com o escritório.">
        <Alert v-if="page.props.flash?.status" tone="success" class="mb-5">{{ page.props.flash.status }}</Alert>
        <Alert v-if="page.props.flash?.error" tone="danger" class="mb-5">{{ page.props.flash.error }}</Alert>

        <form class="grid gap-4" @submit.prevent="submit">
            <TextInput id="portal-email" v-model="form.email" type="email" label="E-mail" required :error="form.errors.email" />
            <TextInput id="portal-password" v-model="form.password" type="password" label="Senha" required :error="form.errors.password" />

            <CheckboxInput v-model="form.remember" label="Manter conectado" />

            <Button type="submit" :loading="form.processing" :disabled="form.processing">Entrar no portal</Button>
        </form>

        <p class="mt-4 text-center text-sm">
            <Link href="/portal/forgot-password" class="font-semibold text-blue-700 hover:text-blue-800">Esqueci minha senha</Link>
        </p>

        <p class="mt-6 text-center text-sm text-slate-500">
            Primeiro acesso?
            <span class="text-slate-700">Use o link de convite enviado pelo escritório.</span>
        </p>
    </AuthLayout>
</template>
