<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import DataTable from '../../Components/Data/DataTable.vue';
import Button from '../../Components/UI/Button.vue';
import Card from '../../Components/UI/Card.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';

defineProps({
    organization: { type: Object, required: true },
    members: { type: Array, default: () => [] },
    invitations: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const page = usePage();

const memberColumns = [
    { key: 'name', label: 'Membro' },
    { key: 'role', label: 'Papel' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' },
];

const invitationColumns = [
    { key: 'email', label: 'Convite' },
    { key: 'role', label: 'Papel' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' },
];

const roleOptions = [
    { value: 'admin', label: 'Administrador' },
    { value: 'manager', label: 'Gerente' },
    { value: 'professional', label: 'Profissional' },
    { value: 'assistant', label: 'Assistente' },
    { value: 'finance', label: 'Financeiro' },
    { value: 'readonly', label: 'Somente leitura' },
];

const inviteForm = useForm({
    name: '',
    email: '',
    role: 'assistant',
});

function roleLabel(role) {
    return roleOptions.find((option) => option.value === role)?.label ?? role;
}

function submitInvitation() {
    inviteForm.post('/organization-invitations', {
        preserveScroll: true,
        onSuccess: () => inviteForm.reset('name', 'email'),
    });
}
</script>

<template>
    <Head title="Equipe" />
    <AppLayout title="Equipe" active-nav="team" :breadcrumbs="[{ label: 'Equipe' }]">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="grid gap-4">
                <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
                <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>
                <Alert v-if="page.props.errors?.member" tone="danger">{{ page.props.errors.member }}</Alert>
                <Alert v-if="page.props.errors?.invitation" tone="danger">{{ page.props.errors.invitation }}</Alert>

                <DataTable :columns="memberColumns" :rows="members" empty-title="Nenhum membro encontrado">
                    <template #cell-name="{ row }">
                        <div class="min-w-56">
                            <p class="font-semibold text-slate-950">{{ row.name }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ row.email }}</p>
                        </div>
                    </template>
                    <template #cell-role="{ row }">
                        <span class="text-sm font-medium text-slate-700">{{ roleLabel(row.role) }}</span>
                    </template>
                    <template #cell-status="{ row }">
                        <StatusPill :status="row.status" />
                    </template>
                    <template #cell-actions="{ row }">
                        <div class="flex justify-end">
                            <Link
                                v-if="row.can_suspend"
                                :href="`/organization-members/${row.id}/suspend`"
                                method="patch"
                                as="button"
                                class="inline-flex h-8 items-center justify-center rounded-lg bg-red-600 px-3 text-[13px] font-semibold text-white hover:bg-red-700"
                            >
                                Suspender
                            </Link>
                            <Link
                                v-if="row.can_reactivate"
                                :href="`/organization-members/${row.id}/reactivate`"
                                method="patch"
                                as="button"
                                class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-[13px] font-semibold text-slate-800 hover:bg-slate-50"
                            >
                                Reativar
                            </Link>
                        </div>
                    </template>
                </DataTable>

                <DataTable :columns="invitationColumns" :rows="invitations" empty-title="Nenhum convite encontrado">
                    <template #toolbar>
                        <div class="border-b border-slate-200 px-4 py-3">
                            <h2 class="text-sm font-semibold text-slate-950">Convites</h2>
                        </div>
                    </template>
                    <template #cell-email="{ row }">
                        <div class="min-w-56">
                            <p class="font-semibold text-slate-950">{{ row.email }}</p>
                            <p v-if="row.name" class="mt-1 text-xs text-slate-500">{{ row.name }}</p>
                        </div>
                    </template>
                    <template #cell-role="{ row }">
                        <span class="text-sm font-medium text-slate-700">{{ roleLabel(row.role) }}</span>
                    </template>
                    <template #cell-status="{ row }">
                        <StatusPill :status="row.status" />
                    </template>
                    <template #cell-actions="{ row }">
                        <div class="flex justify-end">
                            <Link
                                v-if="row.can_cancel"
                                :href="`/organization-invitations/${row.id}`"
                                method="delete"
                                as="button"
                                class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-[13px] font-semibold text-slate-800 hover:bg-slate-50"
                            >
                                Cancelar
                            </Link>
                        </div>
                    </template>
                </DataTable>
            </div>

            <Card title="Convidar membro" :subtitle="organization.name">
                <Alert v-if="!canManage" tone="warning" class="mb-4">Apenas administradores podem convidar, suspender ou reativar membros.</Alert>

                <form class="grid gap-4" @submit.prevent="submitInvitation">
                    <TextInput id="invite-name" v-model="inviteForm.name" label="Nome" :disabled="!canManage" :error="inviteForm.errors.name" />
                    <TextInput id="invite-email" v-model="inviteForm.email" type="email" label="E-mail" required :disabled="!canManage" :error="inviteForm.errors.email" />
                    <SelectInput id="invite-role" v-model="inviteForm.role" label="Papel" :options="roleOptions" :disabled="!canManage" :error="inviteForm.errors.role" />
                    <Button type="submit" :loading="inviteForm.processing" :disabled="!canManage || inviteForm.processing">Enviar convite</Button>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
