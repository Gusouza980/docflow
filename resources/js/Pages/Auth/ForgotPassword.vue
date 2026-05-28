<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Button from '../../Components/UI/Button.vue';
import TextInput from '../../Components/Forms/TextInput.vue';

const page = usePage();

const form = useForm({
    email: '',
});

function submit() {
    form.post('/forgot-password', { preserveScroll: true });
}
</script>

<template>
    <Head title="Recuperar senha" />
    <AuthLayout title="Recuperar senha" subtitle="Informe seu e-mail e enviaremos as instruções caso exista uma conta vinculada.">
        <Alert v-if="page.props.flash?.status" tone="success" class="mb-5">{{ page.props.flash.status }}</Alert>

        <form class="grid gap-4" @submit.prevent="submit">
            <TextInput id="email" v-model="form.email" type="email" label="E-mail" required :error="form.errors.email" />
            <Button type="submit" :loading="form.processing" :disabled="form.processing">Enviar instruções</Button>
            <Link href="/login" class="text-center text-sm font-semibold text-blue-700 hover:text-blue-800">Voltar para o login</Link>
        </form>
    </AuthLayout>
</template>
