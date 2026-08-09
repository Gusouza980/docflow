<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Button from '../../Components/UI/Button.vue';
import Modal from '../../Components/Overlays/Modal.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';

const props = defineProps({
    grouped: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const createOpen = ref(false);
const withEmpty = (items, label) => [{ value: '', label }, ...items];

const createForm = useForm({
    name: '',
    email: '',
    phone: '',
    origin: '',
    stage: 'new',
    estimated_value_cents: '',
    service_interest: '',
});

function submitCreate() {
    createForm.post('/leads', {
        preserveScroll: true,
        onSuccess: () => {
            createOpen.value = false;
            createForm.reset();
        },
    });
}

function money(cents) {
    if (cents == null) return '—';
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(cents / 100);
}
</script>

<template>
    <Head title="CRM — Leads" />
    <AppLayout title="CRM" active-nav="leads" :breadcrumbs="[{ label: 'CRM' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-950">Funil comercial</h2>
                    <p class="mt-1 text-xs text-slate-500">Acompanhe leads por etapa até a conversão.</p>
                </div>
                <Button v-if="can.manage" size="sm" @click="createOpen = true">Novo lead</Button>
            </div>

            <div class="grid gap-3 xl:grid-cols-4">
                <section
                    v-for="stage in options.stages"
                    :key="stage.value"
                    class="rounded-xl border border-slate-200 bg-white"
                >
                    <header class="border-b border-slate-100 px-3 py-2">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-600">{{ stage.label }}</h3>
                        <p class="text-[11px] text-slate-400">{{ (grouped[stage.value] || []).length }} leads</p>
                    </header>
                    <div class="grid gap-2 p-2">
                        <Link
                            v-for="lead in grouped[stage.value] || []"
                            :key="lead.id"
                            :href="`/leads/${lead.id}`"
                            class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 hover:border-slate-300 hover:bg-white"
                        >
                            <p class="text-sm font-semibold text-slate-900">{{ lead.name }}</p>
                            <p class="mt-1 text-[11px] text-slate-500">{{ lead.origin_label || 'Sem origem' }}</p>
                            <p class="mt-1 text-[11px] font-medium text-slate-700">{{ money(lead.estimated_value_cents) }}</p>
                        </Link>
                        <p v-if="!(grouped[stage.value] || []).length" class="px-2 py-4 text-center text-[11px] text-slate-400">Vazio</p>
                    </div>
                </section>
            </div>
        </div>

        <Modal :open="createOpen" title="Novo lead" @close="createOpen = false">
            <form class="grid gap-3" @submit.prevent="submitCreate">
                <TextInput v-model="createForm.name" label="Nome" :error="createForm.errors.name" required />
                <TextInput v-model="createForm.email" label="E-mail" type="email" :error="createForm.errors.email" />
                <TextInput v-model="createForm.phone" label="Telefone" :error="createForm.errors.phone" />
                <SelectInput v-model="createForm.origin" label="Origem" :options="withEmpty(options.origins, 'Selecione')" :error="createForm.errors.origin" />
                <SelectInput v-model="createForm.stage" label="Etapa" :options="options.stages" :error="createForm.errors.stage" />
                <TextInput v-model="createForm.estimated_value_cents" label="Valor estimado (centavos)" :error="createForm.errors.estimated_value_cents" />
                <TextInput v-model="createForm.service_interest" label="Serviço de interesse" :error="createForm.errors.service_interest" />
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" @click="createOpen = false">Cancelar</Button>
                    <Button type="submit" :disabled="createForm.processing">Salvar</Button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
