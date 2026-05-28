<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import Button from '../../Components/UI/Button.vue';
import TextInput from '../../Components/Forms/TextInput.vue';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/reset-password', {
        preserveScroll: true,
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Redefinir senha" />
    <AuthLayout title="Redefinir senha" subtitle="Crie uma nova senha segura para acessar sua conta.">
        <form class="grid gap-4" @submit.prevent="submit">
            <TextInput id="email" v-model="form.email" type="email" label="E-mail" required :error="form.errors.email" />
            <TextInput id="password" v-model="form.password" type="password" label="Nova senha" required :error="form.errors.password" />
            <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" label="Confirmar senha" required :error="form.errors.password_confirmation" />
            <Button type="submit" :loading="form.processing" :disabled="form.processing">Redefinir senha</Button>
            <Link href="/login" class="text-center text-sm font-semibold text-blue-700 hover:text-blue-800">Voltar para o login</Link>
        </form>
    </AuthLayout>
</template>
