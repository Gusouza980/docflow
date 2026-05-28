<script setup>
import { ref } from 'vue';
import Alert from '../../Components/Feedback/Alert.vue';
import Avatar from '../../Components/UI/Avatar.vue';
import Badge from '../../Components/UI/Badge.vue';
import Breadcrumbs from '../../Components/Navigation/Breadcrumbs.vue';
import Button from '../../Components/UI/Button.vue';
import Card from '../../Components/UI/Card.vue';
import CheckboxInput from '../../Components/Forms/CheckboxInput.vue';
import ColorSwatch from '../../Components/StyleGuide/ColorSwatch.vue';
import ComboboxInput from '../../Components/Forms/ComboboxInput.vue';
import CurrencyInput from '../../Components/Forms/CurrencyInput.vue';
import DataTable from '../../Components/Data/DataTable.vue';
import DateInput from '../../Components/Forms/DateInput.vue';
import Drawer from '../../Components/Overlays/Drawer.vue';
import DropdownMenu from '../../Components/Overlays/DropdownMenu.vue';
import EmptyState from '../../Components/Feedback/EmptyState.vue';
import IconButton from '../../Components/UI/IconButton.vue';
import Modal from '../../Components/Overlays/Modal.vue';
import Pagination from '../../Components/Data/Pagination.vue';
import RadioInput from '../../Components/Forms/RadioInput.vue';
import SearchInput from '../../Components/Forms/SearchInput.vue';
import Section from '../../Components/StyleGuide/Section.vue';
import SelectInput from '../../Components/Forms/SelectInput.vue';
import Skeleton from '../../Components/UI/Skeleton.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';
import Tabs from '../../Components/Navigation/Tabs.vue';
import TextareaInput from '../../Components/Forms/TextareaInput.vue';
import TextInput from '../../Components/Forms/TextInput.vue';
import Toast from '../../Components/Feedback/Toast.vue';
import ToggleSwitch from '../../Components/Forms/ToggleSwitch.vue';
import Tooltip from '../../Components/UI/Tooltip.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';

const showModal = ref(false);
const showDrawer = ref(false);
const activeTab = ref('resumo');
const search = ref('documentos');
const clientName = ref('Maria Silva');
const notes = ref('Cliente solicitou revisão documental antes do envio final.');
const responsible = ref('ana');
const deadline = ref('26/04/2026');
const amount = ref('1.250,00');
const selectedClient = ref('Maria Silva');
const requiredChecklist = ref(true);
const radioValue = ref('normal');
const notify = ref(true);

const colors = [
    { name: 'Primary', token: 'brand.primary', hex: '#2563EB', description: 'Ações principais' },
    { name: 'Secondary', token: 'brand.secondary', hex: '#0F766E', description: 'Destaques de apoio' },
    { name: 'Accent', token: 'brand.accent', hex: '#D97706', description: 'Pontos de atenção' },
    { name: 'Surface', token: 'neutral.0', hex: '#FFFFFF', description: 'Superfícies' },
    { name: 'App background', token: 'neutral.50', hex: '#F8FAFC', description: 'Fundo da aplicação' },
    { name: 'Text primary', token: 'neutral.900', hex: '#0F172A', description: 'Texto principal' },
];

const spacing = ['4px', '8px', '12px', '16px', '20px', '24px', '32px', '40px', '48px'];

const selectOptions = [
    { label: 'Ana Costa', value: 'ana' },
    { label: 'Bruno Lima', value: 'bruno' },
    { label: 'Carla Mendes', value: 'carla' },
];

const clientOptions = [
    { label: 'Maria Silva', value: '1' },
    { label: 'Acme Ltda', value: '2' },
    { label: 'João Pereira', value: '3' },
];

const tableColumns = [
    { key: 'client', label: 'Cliente' },
    { key: 'document', label: 'Documento' },
    { key: 'status', label: 'Status' },
    { key: 'due_at', label: 'Prazo' },
    { key: 'priority', label: 'Prioridade' },
    { key: 'actions', label: 'Ações' },
];

const tableRows = [
    { id: 1, client: 'Maria Silva', document: 'Contrato social', status: 'approved', due_at: '26/04/2026', priority: 'Alta' },
    { id: 2, client: 'Acme Ltda', document: 'Comprovante fiscal', status: 'pending', due_at: '29/04/2026', priority: 'Normal' },
    { id: 3, client: 'João Pereira', document: 'Procuração', status: 'overdue', due_at: '24/04/2026', priority: 'Crítica' },
];

const navItems = [
    { key: 'dashboard', label: 'Dashboard', icon: '▦' },
    { key: 'clients', label: 'Clientes', icon: '◎' },
    { key: 'documents', label: 'Documentos', icon: '□' },
    { key: 'tasks', label: 'Tarefas', icon: '✓' },
    { key: 'deadlines', label: 'Prazos', icon: '◷' },
    { key: 'calendar', label: 'Agenda', icon: '◇' },
];

const dropdownItems = [
    { label: 'Visualizar' },
    { label: 'Editar' },
    { label: 'Cancelar solicitação', danger: true },
];

const tabs = [
    { label: 'Resumo', value: 'resumo' },
    { label: 'Documentos', value: 'documentos' },
    { label: 'Histórico', value: 'historico' },
];
</script>

<template>
    <main class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-8 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-center gap-3">
                    <Badge tone="primary">Docflow Web</Badge>
                    <span class="text-sm text-slate-500">Guia vivo de componentes Vue</span>
                </div>
                <div class="grid gap-4 lg:grid-cols-[1fr_360px] lg:items-end">
                    <div>
                        <h1 class="text-3xl font-bold tracking-normal text-slate-950">Guia de Identidade Visual</h1>
                        <p class="mt-3 max-w-3xl text-base leading-7 text-slate-600">
                            Referência visual para a aplicação web em Inertia e Vue, agora composta por componentes reutilizáveis.
                        </p>
                    </div>
                    <Card>
                        <p class="text-xs font-semibold uppercase text-slate-500">Princípios</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <Badge>Clareza</Badge>
                            <Badge>Produtividade</Badge>
                            <Badge>Confiança</Badge>
                            <Badge>Acessibilidade</Badge>
                        </div>
                    </Card>
                </div>
            </div>
        </header>

        <div class="mx-auto grid max-w-7xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[240px_1fr] lg:px-8">
            <aside class="hidden self-start rounded-lg border border-slate-200 bg-white p-3 lg:sticky lg:top-6 lg:block">
                <a
                    v-for="item in ['Cores', 'Tipografia', 'Formulários', 'Feedback', 'Tabelas', 'Navegação', 'Layouts']"
                    :key="item"
                    class="block rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-blue-700"
                    :href="`#${item.toLowerCase()}`"
                >
                    {{ item }}
                </a>
            </aside>

            <div class="space-y-8">
                <Section id="cores" eyebrow="Tokens" title="Cores e status" description="Paleta clara, semântica e adequada a sistemas operacionais usados durante todo o dia.">
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <ColorSwatch v-for="color in colors" :key="color.token" v-bind="color" />
                    </div>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <StatusPill status="pending" />
                        <StatusPill status="in_progress" />
                        <StatusPill status="approved" />
                        <StatusPill status="rejected" />
                        <StatusPill status="overdue" />
                        <StatusPill status="cancelled" />
                        <StatusPill status="critical" />
                    </div>
                </Section>

                <Section id="tipografia" eyebrow="Interface" title="Tipografia, dados e espaçamento" description="Escala enxuta para dashboards, tabelas, formulários e telas internas densas.">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <Card>
                            <p class="text-xs font-semibold uppercase text-slate-500">Display</p>
                            <p class="mt-2 text-3xl font-bold text-slate-950">Clientes, documentos e prazos em um só lugar</p>
                            <p class="mt-4 text-xl font-semibold text-slate-900">Título de seção com leitura rápida</p>
                            <p class="mt-3 text-sm leading-6 text-slate-600">Texto de corpo com line-height confortável para descrições e instruções.</p>
                            <p class="mt-3 text-xs font-medium text-slate-500">Caption para metadados e informações auxiliares</p>
                        </Card>
                        <Card>
                            <p class="text-sm font-semibold text-slate-900">Dados formatados</p>
                            <dl class="mt-4 grid gap-3 text-sm">
                                <div class="flex justify-between gap-4"><dt class="text-slate-500">Moeda</dt><dd class="font-semibold">R$ 1.250,00</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-slate-500">Data</dt><dd class="font-semibold">26/04/2026</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-slate-500">Prazo</dt><dd class="font-semibold text-rose-700">Atrasado há 2 dias</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-slate-500">Documento</dt><dd class="font-semibold">*******8901</dd></div>
                            </dl>
                        </Card>
                    </div>
                    <Card class="mt-4">
                        <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
                            <div v-for="space in spacing" :key="space" class="rounded-md border border-slate-200 p-3">
                                <div class="h-8 rounded bg-blue-100" :style="{ width: space }" />
                                <p class="mt-2 text-xs font-medium text-slate-600">{{ space }}</p>
                            </div>
                        </div>
                    </Card>
                </Section>

                <Section id="formulários" eyebrow="Entrada" title="Formulários e inputs" description="Componentes com labels persistentes, mensagens claras e estados acessíveis.">
                    <Card>
                        <div class="grid gap-4 md:grid-cols-2">
                            <TextInput id="client-name" v-model="clientName" label="Nome do cliente" required />
                            <SelectInput id="responsible" v-model="responsible" label="Responsável" :options="selectOptions" />
                            <DateInput id="deadline" v-model="deadline" label="Data do prazo" />
                            <CurrencyInput id="amount" v-model="amount" label="Valor mensal" />
                            <ComboboxInput id="client-combobox" v-model="selectedClient" label="Combobox de cliente" :options="clientOptions" />
                            <TextInput id="error-sample" label="Campo com erro" model-value="" error="Informe um e-mail válido." />
                            <TextareaInput id="notes" v-model="notes" class="md:col-span-2" label="Observações" />
                        </div>
                        <div class="mt-5 flex flex-wrap gap-5">
                            <CheckboxInput v-model="requiredChecklist" label="Checklist obrigatório" />
                            <RadioInput v-model="radioValue" name="priority" value="normal" label="Prioridade normal" />
                            <ToggleSwitch v-model="notify" label="Notificar responsável" />
                        </div>
                    </Card>
                </Section>

                <Section id="componentes" eyebrow="Ações" title="Botões, avatares, tooltip e badges" description="Componentes base com estados claros e contraste consistente.">
                    <Card>
                        <div class="flex flex-wrap items-center gap-3">
                            <Button>Salvar alterações</Button>
                            <Button variant="secondary">Filtrar</Button>
                            <Button variant="ghost">Cancelar</Button>
                            <Button variant="danger">Excluir</Button>
                            <Button loading>Salvando</Button>
                            <IconButton label="Mais ações">⋯</IconButton>
                            <Tooltip text="Usuário responsável">
                                <Avatar name="Ana Costa" />
                            </Tooltip>
                        </div>
                        <div class="mt-6 flex flex-wrap gap-2">
                            <Badge tone="primary">Cliente ativo</Badge>
                            <Badge tone="secondary">Contabilidade</Badge>
                            <Badge tone="warning">Alta prioridade</Badge>
                            <Badge tone="danger">Restrito</Badge>
                            <Badge tone="success">Aprovado</Badge>
                        </div>
                    </Card>
                </Section>

                <Section id="feedback" eyebrow="Estados" title="Alerts, toasts, dropdowns, tabs e overlays" description="Feedback direto para orientar o usuário sem interromper o fluxo desnecessariamente.">
                    <div class="grid gap-4 xl:grid-cols-2">
                        <div class="space-y-4">
                            <Alert tone="success">Cliente atualizado com sucesso.</Alert>
                            <Alert tone="warning">Este prazo exige revisão antes da conclusão.</Alert>
                            <Alert tone="danger">Não foi possível salvar. Revise os campos destacados.</Alert>
                            <Card>
                                <Tabs v-model="activeTab" :tabs="tabs">
                                    <p class="text-sm text-slate-600">Aba ativa: {{ activeTab }}.</p>
                                </Tabs>
                            </Card>
                        </div>
                        <div class="space-y-4">
                            <Toast tone="success" title="Documento enviado para análise." message="O responsável foi notificado." />
                            <Card>
                                <div class="flex flex-wrap items-center gap-3">
                                    <DropdownMenu :items="dropdownItems" />
                                    <Button variant="secondary" @click="showModal = true">Abrir modal</Button>
                                    <Button variant="secondary" @click="showDrawer = true">Abrir drawer</Button>
                                </div>
                            </Card>
                        </div>
                    </div>
                    <Modal :open="showModal" title="Recusar documento?" description="Informe um motivo claro para orientar o novo envio pelo cliente." @close="showModal = false">
                        <TextareaInput id="modal-reason" label="Motivo da recusa" model-value="Documento ilegível." />
                        <template #actions>
                            <Button variant="danger" @click="showModal = false">Recusar</Button>
                        </template>
                    </Modal>
                    <Drawer :open="showDrawer" title="Filtros avançados" @close="showDrawer = false">
                        <div class="grid gap-4">
                            <SearchInput v-model="search" placeholder="Buscar cliente ou documento" />
                            <SelectInput id="drawer-status" label="Status" model-value="pending" :options="[{ label: 'Pendente', value: 'pending' }, { label: 'Aprovado', value: 'approved' }]" />
                        </div>
                        <template #footer>
                            <div class="flex justify-end gap-3">
                                <Button variant="ghost" @click="showDrawer = false">Cancelar</Button>
                                <Button @click="showDrawer = false">Aplicar filtros</Button>
                            </div>
                        </template>
                    </Drawer>
                </Section>

                <Section id="tabelas" eyebrow="Operação" title="Busca, tabelas, paginação e estados" description="Listagens densas com filtros, status explícitos e ações previsíveis.">
                    <DataTable :columns="tableColumns" :rows="tableRows">
                        <template #toolbar>
                            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 lg:flex-row lg:items-center lg:justify-between">
                                <SearchInput v-model="search" class="max-w-sm" placeholder="Buscar documentos" />
                                <div class="flex flex-wrap gap-2">
                                    <Badge>Status: todos</Badge>
                                    <Badge>Responsável: Ana</Badge>
                                </div>
                            </div>
                        </template>
                        <template #cell-status="{ value }">
                            <StatusPill :status="value" />
                        </template>
                        <template #cell-actions>
                            <IconButton label="Mais ações" size="sm">⋯</IconButton>
                        </template>
                    </DataTable>
                    <Pagination />
                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <EmptyState title="Nenhum documento solicitado" description="Solicite documentos ao cliente para acompanhar pendências por item." action-label="Nova solicitação" />
                        <Card>
                            <Skeleton class-name="h-5 w-40" />
                            <div class="mt-5 space-y-3">
                                <Skeleton class-name="h-10 w-full" />
                                <Skeleton class-name="h-10 w-11/12" />
                                <Skeleton class-name="h-10 w-10/12" />
                            </div>
                        </Card>
                    </div>
                </Section>

                <Section id="navegação" eyebrow="Estrutura" title="Breadcrumbs, sidebar, topbar e layout principal" description="Arquitetura de navegação preparada para módulos operacionais do SaaS.">
                    <Card>
                        <Breadcrumbs :items="[{ label: 'Clientes', href: '#' }, { label: 'Maria Silva', href: '#' }, { label: 'Documentos' }]" />
                    </Card>
                    <div class="mt-4 overflow-hidden rounded-lg border border-slate-200">
                        <AppLayout
                            title="Ficha do cliente"
                            active-nav="clients"
                            :nav-items="navItems"
                            :breadcrumbs="[{ label: 'Clientes', href: '#' }, { label: 'Maria Silva' }]"
                        >
                            <div class="grid gap-4 lg:grid-cols-3">
                                <Card title="Resumo operacional" subtitle="Layout com sidebar, topbar, footer e área principal para telas internas." class="lg:col-span-2">
                                    <p class="text-sm leading-6 text-slate-600">Este preview usa o componente AppLayout real que será reaproveitado nas páginas internas.</p>
                                </Card>
                                <Card>
                                    <p class="text-sm text-slate-500">Pendências</p>
                                    <p class="mt-2 text-2xl font-bold text-slate-950">7</p>
                                </Card>
                            </div>
                        </AppLayout>
                    </div>
                </Section>

                <Section id="layouts" eyebrow="Autenticação" title="Auth layout" description="Base visual para login, recuperação e redefinição de senha.">
                    <div class="overflow-hidden rounded-lg border border-slate-200">
                        <AuthLayout title="Acesse sua conta" subtitle="Entre para organizar clientes, documentos, prazos e finanças.">
                            <div class="grid gap-4">
                                <TextInput id="auth-email" label="E-mail" model-value="ana@example.com" />
                                <TextInput id="auth-password" label="Senha" model-value="••••••••" />
                                <Button>Entrar</Button>
                            </div>
                        </AuthLayout>
                    </div>
                </Section>
            </div>
        </div>
    </main>
</template>
