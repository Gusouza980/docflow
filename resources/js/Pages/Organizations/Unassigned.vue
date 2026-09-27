<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Card from '../../Components/UI/Card.vue';

defineProps({
    title: { type: String, default: 'Sem organização' },
});
</script>

<template>
    <Head title="Sem organização" />
    <AppLayout title="Sem organização" active-nav="organizations">
        <Card title="Você ainda não faz parte de um escritório">
            <p class="text-sm leading-6 text-slate-600">
                O acesso ao Docflow é feito por convite do administrador do escritório.
                Se você espera um convite, confira seu e-mail ou fale com quem provisionou a conta.
            </p>
        </Card>
    </AppLayout>
</template>
