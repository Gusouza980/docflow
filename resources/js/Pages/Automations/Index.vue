<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import DataTable from '../../Components/Data/DataTable.vue';
import Modal from '../../Components/Overlays/Modal.vue';
import Button from '../../Components/UI/Button.vue';
import Badge from '../../Components/UI/Badge.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';

const props = defineProps({
    rules: { type: Array, default: () => [] },
    presets: { type: Array, default: () => [] },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const createModalOpen = ref(false);

const columns = [
    { key: 'name', label: 'Automação' },
    { key: 'trigger', label: 'Gatilho' },
    { key: 'status', label: 'Status' },
    { key: 'logs_count', label: 'Execuções' },
    { key: 'actions', label: '' },
];

const createForm = useForm({
    preset_key: props.presets[0]?.value ?? '',
    name: '',
    task_template_id: '',
    message_template_id: '',
    is_active: true,
});

const selectedPreset = computed(() => props.presets.find((item) => item.value === createForm.preset_key));

function submitCreate() {
    createForm.post('/automations', {
        preserveScroll: true,
        onSuccess: () => {
            createModalOpen.value = false;
            createForm.reset();
        },
    });
}
</script>

<template>
    <Head title="Automações" />
    <AppLayout title="Automações" active-nav="automations" :breadcrumbs="[{ label: 'Automações' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <DataTable :columns="columns" :rows="rules" empty-title="Nenhuma automação configurada">
                <template #toolbar>
                    <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-950">Regras de automação</h2>
                            <p class="mt-1 text-xs text-slate-500">Presets versionados com logs idempotentes.</p>
                        </div>
                        <Button v-if="can.manage" size="sm" @click="createModalOpen = true">Nova automação</Button>
                    </div>
                </template>
                <template #cell-name="{ row }">
                    <p class="font-semibold text-slate-950">{{ row.name }}</p>
                </template>
                <template #cell-trigger="{ row }">{{ row.trigger_label }}</template>
                <template #cell-status="{ row }">
                    <Badge :tone="row.is_active ? 'success' : 'secondary'">{{ row.is_active ? 'Ativa' : 'Pausada' }}</Badge>
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end">
                        <Link :href="row.href" class="text-sm font-semibold underline">Abrir</Link>
                    </div>
                </template>
            </DataTable>
        </div>

        <Modal v-if="createModalOpen" open title="Nova automação" @close="createModalOpen = false">
            <form class="grid gap-3" @submit.prevent="submitCreate">
                <SelectInput id="automation-preset" v-model="createForm.preset_key" label="Preset" :options="presets" required :error="createForm.errors.preset_key" />
                <TextInput id="automation-name" v-model="createForm.name" label="Nome (opcional)" :error="createForm.errors.name" />
                <SelectInput
                    v-if="selectedPreset?.value === 'client_created_tasks'"
                    id="automation-template"
                    v-model="createForm.task_template_id"
                    label="Modelo de tarefas"
                    :options="options.task_templates"
                    required
                    :error="createForm.errors.task_template_id"
                />
                <SelectInput
                    v-if="selectedPreset?.value === 'receivable_overdue_email'"
                    id="automation-message-template"
                    v-model="createForm.message_template_id"
                    label="Modelo de e-mail"
                    :options="options.message_templates"
                    required
                    :error="createForm.errors.message_template_id"
                />
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" @click="createModalOpen = false">Cancelar</Button>
                    <Button type="submit" :disabled="createForm.processing">Salvar</Button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
