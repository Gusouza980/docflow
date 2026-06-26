<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import ClientPortalLayout from '../../Layouts/ClientPortalLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Button from '../../Components/UI/Button.vue';
import TextInput from '../../Components/Forms/TextInput.vue';

const props = defineProps({
    profile: { type: Object, required: true },
});

const form = useForm({
    name: props.profile.name,
    email: props.profile.email,
    phone: props.profile.phone ?? '',
    whatsapp: props.profile.whatsapp ?? '',
});

function submitProfile() {
    form.patch('/client-portal/profile', { preserveScroll: true });
}
</script>

<template>
    <Head title="Meus dados · Portal" />
    <ClientPortalLayout title="Meus dados" active-nav="more">
        <div class="mx-auto max-w-xl grid gap-6">
            <Alert v-if="profile.pending_request" tone="warning">
                Alterações de e-mail ou telefone aguardam revisão da equipe desde {{ profile.pending_request.created_at }}.
            </Alert>

            <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="submitProfile">
                <h2 class="text-base font-semibold text-slate-950">Dados de contato</h2>
                <p class="mt-1 text-sm text-slate-500">O nome é atualizado imediatamente. E-mail e telefones passam por revisão interna.</p>

                <div class="mt-4 grid gap-4">
                    <TextInput id="profile-name" v-model="form.name" label="Nome" :error="form.errors.name" required />
                    <TextInput id="profile-email" v-model="form.email" type="email" label="E-mail" :error="form.errors.email" />
                    <TextInput id="profile-phone" v-model="form.phone" label="Telefone" :error="form.errors.phone" />
                    <TextInput id="profile-whatsapp" v-model="form.whatsapp" label="WhatsApp" :error="form.errors.whatsapp" />
                </div>

                <div class="mt-5 flex justify-end">
                    <Button type="submit" :loading="form.processing">Salvar alterações</Button>
                </div>
            </form>
        </div>
    </ClientPortalLayout>
</template>
