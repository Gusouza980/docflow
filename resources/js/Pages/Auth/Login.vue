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
    form.post('/login', {
        preserveScroll: true,
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Entrar" />
    <AuthLayout title="Acesse sua conta" subtitle="Entre para organizar clientes, documentos, prazos e finanças em um só lugar.">
        <Alert v-if="page.props.flash?.status" tone="success" class="mb-5">{{ page.props.flash.status }}</Alert>

        <form class="grid gap-4" @submit.prevent="submit">
            <TextInput id="email" v-model="form.email" type="email" label="E-mail" required :error="form.errors.email" />
            <TextInput id="password" v-model="form.password" type="password" label="Senha" required :error="form.errors.password" />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <CheckboxInput v-model="form.remember" label="Manter conectado" />
                <Link href="/forgot-password" class="text-sm font-semibold text-blue-700 hover:text-blue-800">
                    Esqueci minha senha
                </Link>
            </div>

            <Button type="submit" :loading="form.processing" :disabled="form.processing">Entrar</Button>
        </form>
    </AuthLayout>
</template>
