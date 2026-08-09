<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Button from '../../Components/UI/Button.vue';
import Modal from '../../Components/Overlays/Modal.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import TextareaInput from '../../Components/Forms/TextareaInput.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';
import DataTable from '../../Components/Data/DataTable.vue';

const props = defineProps({
    templates: { type: Array, default: () => [] },
    clients: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const createOpen = ref(false);
const startOpen = ref(false);
const selectedTemplate = ref(null);

const columns = [
    { key: 'name', label: 'Template' },
    { key: 'items', label: 'Itens' },
    { key: 'actions', label: '' },
];

const createForm = useForm({
    name: '',
    description: '',
    is_active: true,
    items: [{ title: '', description: '', due_in_days: 0 }],
});

const startForm = useForm({
    client_id: '',
});

const clientOptions = props.clients.map((client) => ({ value: client.id, label: client.display_name }));

function addItem() {
    createForm.items.push({ title: '', description: '', due_in_days: 0 });
}

function submitCreate() {
    createForm.post('/onboarding-templates', {
        preserveScroll: true,
        onSuccess: () => {
            createOpen.value = false;
            createForm.reset();
            createForm.items = [{ title: '', description: '', due_in_days: 0 }];
        },
    });
}

function openStart(template) {
    selectedTemplate.value = template;
    startForm.reset();
    startOpen.value = true;
}

function submitStart() {
    startForm.post(`/onboarding-templates/${selectedTemplate.value.id}/start`, {
        preserveScroll: true,
        onSuccess: () => {
            startOpen.value = false;
        },
    });
}
</script>

<template>
    <Head title="Onboarding" />
    <AppLayout title="Onboarding" active-nav="onboarding-templates" :breadcrumbs="[{ label: 'Onboarding' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <DataTable :columns="columns" :rows="templates" empty-title="Nenhum template de onboarding">
                <template #toolbar>
                    <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-950">Templates de onboarding</h2>
                            <p class="mt-1 text-xs text-slate-500">Gere tarefas iniciais ao fechar um cliente.</p>
                        </div>
                        <Button v-if="can.manage" size="sm" @click="createOpen = true">Novo template</Button>
                    </div>
                </template>
                <template #cell-name="{ row }">
                    <div>
                        <p class="font-semibold text-slate-950">{{ row.name }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ row.description || 'Sem descrição' }}</p>
                    </div>
                </template>
                <template #cell-items="{ row }">{{ row.items.length }} itens</template>
                <template #cell-actions="{ row }">
                    <Button size="sm" variant="secondary" @click="openStart(row)">Aplicar</Button>
                </template>
            </DataTable>
        </div>

        <Modal :open="createOpen" title="Novo template" @close="createOpen = false">
            <form class="grid gap-3" @submit.prevent="submitCreate">
                <TextInput v-model="createForm.name" label="Nome" :error="createForm.errors.name" />
                <TextareaInput v-model="createForm.description" label="Descrição" :error="createForm.errors.description" />
                <div v-for="(item, index) in createForm.items" :key="index" class="grid gap-2 rounded-lg border border-slate-200 p-3">
                    <TextInput v-model="item.title" :label="`Item ${index + 1}`" :error="createForm.errors[`items.${index}.title`]" />
                    <TextInput v-model="item.due_in_days" label="Prazo (dias)" />
                    <TextareaInput v-model="item.description" label="Descrição" />
                </div>
                <Button type="button" variant="secondary" size="sm" @click="addItem">Adicionar item</Button>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" @click="createOpen = false">Cancelar</Button>
                    <Button type="submit" :disabled="createForm.processing">Salvar</Button>
                </div>
            </form>
        </Modal>

        <Modal :open="startOpen" title="Aplicar onboarding" @close="startOpen = false">
            <form class="grid gap-3" @submit.prevent="submitStart">
                <SelectInput v-model="startForm.client_id" label="Cliente" :options="clientOptions" :error="startForm.errors.client_id" />
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="secondary" @click="startOpen = false">Cancelar</Button>
                    <Button type="submit" :disabled="startForm.processing">Criar tarefas</Button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
