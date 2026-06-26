<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Button from '../../Components/UI/Button.vue';
import CheckboxInput from '../../Components/Forms/CheckboxInput.vue';
import TextInput from '../../Components/Forms/TextInput.vue';

defineProps({
    client: { type: Object, required: true },
    contact: { type: Object, required: true },
    organization: { type: Object, required: true },
});

const page = usePage();

const form = useForm({
    password: '',
    password_confirmation: '',
    accept_terms: false,
});

function submit() {
    form.post('/client-portal/onboarding', {
        preserveScroll: true,
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Configurar acesso ao portal" />
    <AuthLayout
        title="Configure seu acesso"
        :subtitle="`Olá, ${contact.name}. Crie uma senha para acessar o portal de ${client.name} em ${organization.name}.`"
    >
        <Alert v-if="page.props.flash?.error" tone="danger" class="mb-5">{{ page.props.flash.error }}</Alert>

        <form class="grid gap-4" @submit.prevent="submit">
            <TextInput id="onboarding-email" :model-value="contact.email" type="email" label="E-mail" disabled />
            <TextInput id="onboarding-password" v-model="form.password" type="password" label="Senha" required :error="form.errors.password" />
            <TextInput id="onboarding-password-confirmation" v-model="form.password_confirmation" type="password" label="Confirmar senha" required />
            <CheckboxInput v-model="form.accept_terms" label="Aceito receber comunicações pelo portal e manter meus dados atualizados." :error="form.errors.accept_terms" />
            <Button type="submit" :loading="form.processing" :disabled="form.processing">Criar senha e entrar</Button>
        </form>
    </AuthLayout>
</template>
