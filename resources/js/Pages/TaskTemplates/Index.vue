<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import DataTable from '../../Components/Data/DataTable.vue';
import Modal from '../../Components/Overlays/Modal.vue';
import Button from '../../Components/UI/Button.vue';
import Badge from '../../Components/UI/Badge.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';
import TextareaInput from '../../Components/Forms/TextareaInput.vue';

const props = defineProps({
    templates: { type: Array, default: () => [] },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const createModalOpen = ref(false);
const editModalOpen = ref(false);
const applyModalOpen = ref(false);
const selectedTemplate = ref(null);
const withEmpty = (items, label) => [{ value: '', label }, ...items];
const priorityOptions = [
    { value: 'low', label: 'Baixa' },
    { value: 'normal', label: 'Normal' },
    { value: 'high', label: 'Alta' },
    { value: 'critical', label: 'Crítica' },
];
const columns = [
    { key: 'name', label: 'Modelo' },
    { key: 'priority', label: 'Prioridade' },
    { key: 'items', label: 'Itens' },
    { key: 'actions', label: '' },
];
const createForm = useForm({
    name: '',
    description: '',
    priority: 'normal',
    is_active: true,
    items: [{ title: '', description: '', due_in_days: 0, priority: 'normal' }],
});
const editForm = useForm({
    name: '',
    description: '',
    priority: 'normal',
    is_active: true,
    items: [],
});
const applyForm = useForm({
    client_id: '',
    assigned_to_member_id: '',
    base_date: '',
});

function addItem() {
    createForm.items.push({ title: '', description: '', due_in_days: 0, priority: createForm.priority });
}

function addEditItem() {
    editForm.items.push({ title: '', description: '', due_in_days: 0, priority: editForm.priority });
}

function removeItem(index) {
    if (createForm.items.length > 1) {
        createForm.items.splice(index, 1);
    }
}

function removeEditItem(index) {
    if (editForm.items.length > 1) {
        editForm.items.splice(index, 1);
    }
}

function submitCreate() {
    createForm.post('/task-templates', { preserveScroll: true, onSuccess: () => createModalOpen.value = false });
}

function openEdit(template) {
    selectedTemplate.value = template;
    editForm.clearErrors();
    editForm.name = template.name;
    editForm.description = template.description ?? '';
    editForm.priority = template.priority ?? 'normal';
    editForm.is_active = template.is_active;
    editForm.items = template.items.map((item) => ({
        title: item.title,
        description: item.description ?? '',
        due_in_days: item.due_in_days ?? 0,
        priority: item.priority ?? template.priority ?? 'normal',
    }));
    editModalOpen.value = true;
}

function submitEdit() {
    editForm.patch(`/task-templates/${selectedTemplate.value.id}`, { preserveScroll: true, onSuccess: () => editModalOpen.value = false });
}

function openApply(template) {
    selectedTemplate.value = template;
    applyForm.reset();
    applyModalOpen.value = true;
}

function submitApply() {
    applyForm.post(`/task-templates/${selectedTemplate.value.id}/create-tasks`, { preserveScroll: true, onSuccess: () => applyModalOpen.value = false });
}
</script>

<template>
    <Head title="Modelos de tarefas" />
    <AppLayout title="Modelos" active-nav="task-templates" :breadcrumbs="[{ label: 'Modelos' }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>
            <DataTable :columns="columns" :rows="templates" empty-title="Nenhum modelo encontrado">
                <template #toolbar><div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-sm font-semibold text-slate-950">Modelos de tarefas</h2><p class="mt-1 text-xs text-slate-500">Crie rotinas reutilizáveis com prazos relativos.</p></div><Button v-if="can.create" size="sm" @click="createModalOpen = true">Novo modelo</Button></div></template>
                <template #cell-name="{ row }"><div><p class="font-semibold text-slate-950">{{ row.name }}</p><p class="mt-1 text-xs text-slate-500">{{ row.description || 'Sem descrição' }}</p></div></template>
                <template #cell-priority="{ row }"><Badge :tone="row.priority === 'critical' ? 'danger' : 'secondary'">{{ row.priority }}</Badge></template>
                <template #cell-items="{ row }">{{ row.items.length }} itens</template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end gap-2">
                        <Button v-if="can.update" size="sm" variant="secondary" @click="openEdit(row)">Editar</Button>
                        <Button size="sm" variant="secondary" @click="openApply(row)">Criar tarefas</Button>
                    </div>
                </template>
            </DataTable>
        </div>

        <Modal v-if="createModalOpen" open title="Novo modelo" @close="createModalOpen = false">
            <form id="template-form" class="grid gap-4" @submit.prevent="submitCreate">
                <TextInput id="template-name" v-model="createForm.name" label="Nome" required :error="createForm.errors.name" />
                <TextareaInput id="template-description" v-model="createForm.description" label="Descrição" :error="createForm.errors.description" />
                <SelectInput id="template-priority" v-model="createForm.priority" label="Prioridade padrão" :options="priorityOptions" :error="createForm.errors.priority" />
                <div class="grid gap-3">
                    <div v-for="(item, index) in createForm.items" :key="index" class="grid gap-3 rounded-lg border border-slate-200 p-3">
                        <div class="flex items-center justify-between gap-3"><p class="text-sm font-semibold text-slate-950">Item {{ index + 1 }}</p><Button size="sm" variant="ghost" @click="removeItem(index)">Remover</Button></div>
                        <TextInput :id="`template-item-title-${index}`" v-model="item.title" label="Título" required :error="createForm.errors[`items.${index}.title`]" />
                        <TextareaInput :id="`template-item-description-${index}`" v-model="item.description" label="Descrição" :error="createForm.errors[`items.${index}.description`]" />
                        <div class="grid gap-3 sm:grid-cols-2"><TextInput :id="`template-item-due-${index}`" v-model="item.due_in_days" type="number" label="Prazo relativo (dias)" :error="createForm.errors[`items.${index}.due_in_days`]" /><SelectInput :id="`template-item-priority-${index}`" v-model="item.priority" label="Prioridade" :options="priorityOptions" :error="createForm.errors[`items.${index}.priority`]" /></div>
                    </div>
                    <Button variant="secondary" @click="addItem">Adicionar item</Button>
                </div>
            </form>
            <template #actions><Button type="submit" form="template-form" :loading="createForm.processing">Salvar</Button></template>
        </Modal>

        <Modal v-if="editModalOpen" open title="Editar modelo" @close="editModalOpen = false">
            <form id="edit-template-form" class="grid gap-4" @submit.prevent="submitEdit">
                <TextInput id="edit-template-name" v-model="editForm.name" label="Nome" required :error="editForm.errors.name" />
                <TextareaInput id="edit-template-description" v-model="editForm.description" label="Descrição" :error="editForm.errors.description" />
                <SelectInput id="edit-template-priority" v-model="editForm.priority" label="Prioridade padrão" :options="priorityOptions" :error="editForm.errors.priority" />
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input v-model="editForm.is_active" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-300" />
                    Modelo ativo
                </label>
                <div class="grid gap-3">
                    <div v-for="(item, index) in editForm.items" :key="index" class="grid gap-3 rounded-lg border border-slate-200 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-slate-950">Item {{ index + 1 }}</p>
                            <Button size="sm" variant="ghost" @click="removeEditItem(index)">Remover</Button>
                        </div>
                        <TextInput :id="`edit-template-item-title-${index}`" v-model="item.title" label="Título" required :error="editForm.errors[`items.${index}.title`]" />
                        <TextareaInput :id="`edit-template-item-description-${index}`" v-model="item.description" label="Descrição" :error="editForm.errors[`items.${index}.description`]" />
                        <div class="grid gap-3 sm:grid-cols-2">
                            <TextInput :id="`edit-template-item-due-${index}`" v-model="item.due_in_days" type="number" label="Prazo relativo (dias)" :error="editForm.errors[`items.${index}.due_in_days`]" />
                            <SelectInput :id="`edit-template-item-priority-${index}`" v-model="item.priority" label="Prioridade" :options="priorityOptions" :error="editForm.errors[`items.${index}.priority`]" />
                        </div>
                    </div>
                    <Button variant="secondary" @click="addEditItem">Adicionar item</Button>
                </div>
            </form>
            <template #actions><Button type="submit" form="edit-template-form" :loading="editForm.processing">Salvar</Button></template>
        </Modal>

        <Modal v-if="applyModalOpen" open title="Criar tarefas do modelo" @close="applyModalOpen = false">
            <form id="apply-template-form" class="grid gap-4" @submit.prevent="submitApply">
                <SelectInput id="apply-template-client" v-model="applyForm.client_id" label="Cliente" :options="withEmpty(options.clients, 'Sem cliente')" :error="applyForm.errors.client_id" />
                <SelectInput id="apply-template-member" v-model="applyForm.assigned_to_member_id" label="Responsável" :options="withEmpty(options.members, 'Selecione')" :error="applyForm.errors.assigned_to_member_id" />
                <TextInput id="apply-template-date" v-model="applyForm.base_date" type="date" label="Data base" :error="applyForm.errors.base_date" />
            </form>
            <template #actions><Button type="submit" form="apply-template-form" :loading="applyForm.processing">Criar tarefas</Button></template>
        </Modal>
    </AppLayout>
</template>
