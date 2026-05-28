<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Card from '../../Components/UI/Card.vue';
import Button from '../../Components/UI/Button.vue';
import Badge from '../../Components/UI/Badge.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';
import Modal from '../../Components/Overlays/Modal.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';
import TextareaInput from '../../Components/Forms/TextareaInput.vue';

const props = defineProps({
    task: { type: Object, required: true },
    options: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const page = usePage();
const editModalOpen = ref(false);
const checklistModalOpen = ref(false);
const completeModalOpen = ref(false);
const withEmpty = (items, label) => [{ value: '', label }, ...items];
const statusOptions = [
    { value: 'pending', label: 'Pendente' },
    { value: 'in_progress', label: 'Em andamento' },
    { value: 'blocked', label: 'Bloqueada' },
    { value: 'cancelled', label: 'Cancelada' },
];
const editStatusOptions = [...statusOptions, { value: 'completed', label: 'Concluída' }];
const priorityOptions = [
    { value: 'low', label: 'Baixa' },
    { value: 'normal', label: 'Normal' },
    { value: 'high', label: 'Alta' },
    { value: 'critical', label: 'Crítica' },
];

const editForm = useForm({
    client_id: props.task.client?.id ?? '',
    assigned_to_member_id: props.task.assignee?.id ?? '',
    title: props.task.title,
    description: props.task.description ?? '',
    status: props.task.status,
    priority: props.task.priority,
    due_at: props.task.due_at,
});
const statusForm = useForm({ status: props.task.status });
const checklistForm = useForm({ title: '', is_required: false });
const completeForm = useForm({ completion_notes: '' });

function submitEdit() {
    editForm.patch(`/tasks/${props.task.id}`, { preserveScroll: true, onSuccess: () => editModalOpen.value = false });
}

function submitStatus() {
    statusForm.patch(`/tasks/${props.task.id}/status`, { preserveScroll: true });
}

function submitChecklist() {
    checklistForm.post(`/tasks/${props.task.id}/checklist-items`, {
        preserveScroll: true,
        onSuccess: () => checklistModalOpen.value = false,
    });
}

function toggleChecklist(item) {
    useForm({ is_completed: !item.is_completed }).patch(`/task-checklist-items/${item.id}`, { preserveScroll: true });
}

function submitComplete() {
    completeForm.patch(`/tasks/${props.task.id}/complete`, { preserveScroll: true, onSuccess: () => completeModalOpen.value = false });
}
</script>

<template>
    <Head :title="task.title" />
    <AppLayout :title="task.title" active-nav="tasks" :breadcrumbs="[{ label: 'Tarefas', href: '/tasks' }, { label: task.title }]">
        <div class="grid gap-4">
            <Alert v-if="page.props.flash?.status" tone="success">{{ page.props.flash.status }}</Alert>
            <Alert v-if="page.props.flash?.error" tone="danger">{{ page.props.flash.error }}</Alert>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <StatusPill :status="task.status" />
                        <Badge :tone="task.priority === 'critical' ? 'danger' : 'secondary'">{{ task.priority }}</Badge>
                        <Badge v-if="task.is_overdue" tone="danger">Atrasada</Badge>
                    </div>
                    <p class="mt-2 text-sm text-slate-500">{{ task.description || 'Sem descrição' }}</p>
                </div>
                <div v-if="can.update" class="flex flex-wrap gap-2">
                    <Button variant="secondary" @click="editModalOpen = true">Editar</Button>
                    <Button @click="completeModalOpen = true">Concluir</Button>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                <Card title="Dados">
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-xs font-semibold uppercase text-slate-500">Cliente</dt><dd class="mt-1 text-sm text-slate-900">{{ task.client?.name ?? 'Sem cliente' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase text-slate-500">Responsável</dt><dd class="mt-1 text-sm text-slate-900">{{ task.assignee?.name ?? 'Sem responsável' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase text-slate-500">Prazo</dt><dd class="mt-1 text-sm text-slate-900">{{ task.due_at }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase text-slate-500">Checklist</dt><dd class="mt-1 text-sm text-slate-900">{{ task.checklist_progress }}</dd></div>
                    </dl>
                    <form v-if="can.update" class="mt-5 flex flex-wrap items-end gap-3" @submit.prevent="submitStatus">
                        <SelectInput id="task-status-action" v-model="statusForm.status" label="Alterar status" :options="statusOptions" />
                        <Button type="submit" variant="secondary" :loading="statusForm.processing">Atualizar</Button>
                    </form>
                </Card>

                <Card title="Checklist">
                    <template #actions><Button v-if="can.update" size="sm" variant="secondary" @click="checklistModalOpen = true">Adicionar</Button></template>
                    <div class="grid gap-2">
                        <button v-for="item in task.checklist_items" :key="item.id" type="button" class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 p-3 text-left hover:bg-slate-50" @click="can.update && toggleChecklist(item)">
                            <span class="text-sm font-medium text-slate-800">{{ item.title }}</span>
                            <span class="text-xs text-slate-500">{{ item.is_completed ? 'Concluído' : (item.is_required ? 'Obrigatório' : 'Opcional') }}</span>
                        </button>
                    </div>
                </Card>
            </div>
        </div>

        <Modal v-if="editModalOpen" open title="Editar tarefa" @close="editModalOpen = false">
            <form id="edit-task-form" class="grid gap-4" @submit.prevent="submitEdit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <SelectInput id="edit-task-client" v-model="editForm.client_id" label="Cliente" :options="withEmpty(options.clients, 'Sem cliente')" :error="editForm.errors.client_id" />
                    <SelectInput id="edit-task-assignee" v-model="editForm.assigned_to_member_id" label="Responsável" :options="withEmpty(options.members, 'Selecione')" :error="editForm.errors.assigned_to_member_id" />
                </div>
                <TextInput id="edit-task-title" v-model="editForm.title" label="Título" required :error="editForm.errors.title" />
                <TextareaInput id="edit-task-description" v-model="editForm.description" label="Descrição" :error="editForm.errors.description" />
                <div class="grid gap-4 sm:grid-cols-3">
                    <SelectInput id="edit-task-status" v-model="editForm.status" label="Status" :options="editStatusOptions" :error="editForm.errors.status" />
                    <SelectInput id="edit-task-priority" v-model="editForm.priority" label="Prioridade" :options="priorityOptions" :error="editForm.errors.priority" />
                    <TextInput id="edit-task-due" v-model="editForm.due_at" type="date" label="Prazo" required :error="editForm.errors.due_at" />
                </div>
            </form>
            <template #actions><Button type="submit" form="edit-task-form" :loading="editForm.processing">Salvar</Button></template>
        </Modal>

        <Modal v-if="checklistModalOpen" open title="Adicionar item" @close="checklistModalOpen = false">
            <form id="checklist-form" class="grid gap-4" @submit.prevent="submitChecklist">
                <TextInput id="checklist-title" v-model="checklistForm.title" label="Título" required :error="checklistForm.errors.title" />
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700"><input v-model="checklistForm.is_required" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-300" />Obrigatório</label>
            </form>
            <template #actions><Button type="submit" form="checklist-form" :loading="checklistForm.processing">Adicionar</Button></template>
        </Modal>

        <Modal v-if="completeModalOpen" open title="Concluir tarefa" @close="completeModalOpen = false">
            <form id="complete-task-form" class="grid gap-4" @submit.prevent="submitComplete">
                <TextareaInput id="task-completion-notes" v-model="completeForm.completion_notes" label="Notas de conclusão" :error="completeForm.errors.completion_notes" />
            </form>
            <template #actions><Button type="submit" form="complete-task-form" :loading="completeForm.processing">Concluir</Button></template>
        </Modal>
    </AppLayout>
</template>
