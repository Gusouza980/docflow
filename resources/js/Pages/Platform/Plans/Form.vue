<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import PlatformLayout from '../../../Layouts/PlatformLayout.vue';
import Alert from '../../../Components/Feedback/Alert.vue';
import Button from '../../../Components/UI/Button.vue';
import Card from '../../../Components/UI/Card.vue';
import TextInput from '../../../Components/Forms/TextInput.vue';
import TextareaInput from '../../../Components/Forms/TextareaInput.vue';

const props = defineProps({
    plan: { type: Object, default: null },
    limitKeys: { type: Array, default: () => [] },
    featureKeys: { type: Array, default: () => [] },
});

const page = usePage();
const isEditing = computed(() => Boolean(props.plan?.id));

const form = useForm({
    slug: props.plan?.slug ?? '',
    name: props.plan?.name ?? '',
    description: props.plan?.description ?? '',
    price_cents: props.plan?.price_cents ?? 0,
    billing_interval: props.plan?.billing_interval ?? 'month',
    trial_days: props.plan?.trial_days ?? 14,
    limits: {
        max_members: props.plan?.limits?.max_members ?? 3,
        max_clients: props.plan?.limits?.max_clients ?? 50,
        max_storage_mb: props.plan?.limits?.max_storage_mb ?? 2048,
        max_portal_accesses: props.plan?.limits?.max_portal_accesses ?? 10,
    },
    features: Object.fromEntries(
        (props.featureKeys.length
            ? props.featureKeys
            : ['portal', 'finance_advanced', 'reports_scheduling', 'audit', 'automations', 'crm']
        ).map((key) => [key, Boolean(props.plan?.features?.[key])]),
    ),
    is_public: props.plan?.is_public ?? true,
    is_active: props.plan?.is_active ?? true,
    sort_order: props.plan?.sort_order ?? 1,
});

function submit() {
    if (isEditing.value) {
        form.patch(`/platform/plans/${props.plan.id}`, { preserveScroll: true });
        return;
    }

    form.post('/platform/plans', { preserveScroll: true });
}
</script>

<template>
    <Head :title="isEditing ? `Editar ${plan.name}` : 'Novo plano'" />
    <PlatformLayout :title="isEditing ? `Editar ${plan.name}` : 'Novo plano'" active-nav="plans">
        <form class="grid max-w-3xl gap-4" @submit.prevent="submit">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Card title="Dados gerais">
                <div class="grid gap-3">
                    <TextInput id="plan-slug" v-model="form.slug" label="Slug" required :error="form.errors.slug" />
                    <TextInput id="plan-name" v-model="form.name" label="Nome" required :error="form.errors.name" />
                    <TextareaInput id="plan-description" v-model="form.description" label="Descrição" :error="form.errors.description" />
                    <div class="grid gap-3 sm:grid-cols-3">
                        <TextInput id="plan-price" v-model="form.price_cents" type="number" min="0" label="Preço (centavos)" required :error="form.errors.price_cents" />
                        <TextInput id="plan-trial" v-model="form.trial_days" type="number" min="0" label="Trial (dias)" required :error="form.errors.trial_days" />
                        <TextInput id="plan-sort" v-model="form.sort_order" type="number" min="0" label="Ordem" :error="form.errors.sort_order" />
                    </div>
                </div>
            </Card>
            <Card title="Limites (-1 = ilimitado)">
                <div class="grid gap-3 sm:grid-cols-2">
                    <TextInput id="limit-members" v-model="form.limits.max_members" type="number" label="Membros" />
                    <TextInput id="limit-clients" v-model="form.limits.max_clients" type="number" label="Clientes" />
                    <TextInput id="limit-storage" v-model="form.limits.max_storage_mb" type="number" label="Storage (MB)" />
                    <TextInput id="limit-portal" v-model="form.limits.max_portal_accesses" type="number" label="Acessos portal" />
                </div>
            </Card>
            <Card title="Features">
                <div class="grid gap-2 sm:grid-cols-2">
                    <label v-for="key in featureKeys" :key="key" class="flex items-center gap-2 text-sm">
                        <input v-model="form.features[key]" type="checkbox" class="rounded border-slate-300" />
                        {{ key }}
                    </label>
                </div>
            </Card>
            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">{{ isEditing ? 'Salvar' : 'Criar plano' }}</Button>
                <Link href="/platform/plans"><Button variant="secondary" type="button">Voltar</Button></Link>
            </div>
        </form>
    </PlatformLayout>
</template>
