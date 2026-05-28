<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Badge from '../../Components/UI/Badge.vue';
import Card from '../../Components/UI/Card.vue';
import StatusPill from '../../Components/UI/StatusPill.vue';

const navigation = [
    { id: 'visao-geral', label: 'Visão geral' },
    { id: 'papeis', label: 'Papéis' },
    { id: 'jornadas', label: 'Jornadas' },
    { id: 'modulos', label: 'Módulos' },
    { id: 'portal', label: 'Portal do cliente' },
    { id: 'relatorios', label: 'Relatórios' },
    { id: 'rotina', label: 'Rotina recomendada' },
];

const roles = [
    { name: 'Administrador', badge: 'admin', tone: 'primary', summary: 'Configura a organização, gerencia equipe, opera todos os módulos e mantém controle completo da conta.' },
    { name: 'Gestor', badge: 'manager', tone: 'secondary', summary: 'Acompanha a operação, acessa clientes restritos, gerencia tarefas, documentos, portal, relatórios e financeiro.' },
    { name: 'Profissional', badge: 'professional', tone: 'neutral', summary: 'Atua nos clientes sob sua responsabilidade, movimentando tarefas, documentos, prazos e agenda.' },
    { name: 'Assistente', badge: 'assistant', tone: 'warning', summary: 'Apoia a execução operacional em clientes acessíveis, sem acesso ao módulo financeiro.' },
    { name: 'Financeiro', badge: 'finance', tone: 'success', summary: 'Registra cobranças, pagamentos, despesas, inadimplência e consulta indicadores financeiros.' },
    { name: 'Somente leitura', badge: 'readonly', tone: 'neutral', summary: 'Consulta informações permitidas sem criar, editar, concluir, cancelar ou excluir registros.' },
];

const journeys = [
    {
        title: 'Implantação inicial do escritório',
        description: 'Fluxo recomendado para começar a usar o Docflow com a equipe.',
        steps: ['Criar ou selecionar a organização.', 'Convidar membros da equipe e definir papéis.', 'Cadastrar clientes prioritários.', 'Criar categorias documentais e financeiras.', 'Configurar modelos de tarefas recorrentes.', 'Usar o dashboard para acompanhar pendências iniciais.'],
    },
    {
        title: 'Onboarding de novo cliente',
        description: 'Organize dados, documentos e atividades de entrada de forma controlada.',
        steps: ['Cadastrar cliente PF ou PJ.', 'Definir responsável principal e contatos.', 'Aplicar etiquetas e nível de risco.', 'Criar solicitação documental com prazos.', 'Gerar tarefas por modelo de onboarding.', 'Criar acesso ao portal quando o cliente for acompanhar pendências.'],
    },
    {
        title: 'Solicitação documental completa',
        description: 'Do pedido ao cliente até a aprovação do arquivo recebido.',
        steps: ['Criar solicitação documental para o cliente.', 'Adicionar itens, instruções e vencimentos.', 'Receber arquivos internamente ou pelo portal.', 'Aprovar documentos corretos.', 'Recusar documentos inválidos com motivo claro.', 'Acompanhar vencidos no dashboard e relatórios.'],
    },
    {
        title: 'Relatório mensal para cliente',
        description: 'Consolide atividades e libere uma visão mensal no portal.',
        steps: ['Acessar Relatórios.', 'Selecionar cliente e período.', 'Gerar relatório mensal.', 'Revisar tarefas, documentos, chamados e financeiro.', 'Liberar relatório para o portal.', 'Cliente visualiza o relatório no próprio acesso externo.'],
    },
];

const modules = [
    {
        title: 'Dashboard',
        route: '/dashboard',
        meaning: 'Painel de indicadores e alertas críticos da organização ativa.',
        actions: ['Ver clientes ativos, inadimplentes e de alto risco.', 'Identificar tarefas atrasadas.', 'Acompanhar documentos vencidos e próximos do vencimento.', 'Acessar pendências estruturais de clientes.'],
        connection: 'Resume dados de clientes, tarefas, documentos, chamados e financeiro para orientar a rotina diária.',
    },
    {
        title: 'Clientes',
        route: '/clients',
        meaning: 'Base operacional de pessoas físicas e jurídicas atendidas pelo escritório.',
        actions: ['Cadastrar e editar clientes.', 'Controlar status, prioridade e risco.', 'Adicionar contatos, responsáveis e etiquetas.', 'Restringir acesso a clientes sensíveis.'],
        connection: 'Cliente é o eixo que conecta documentos, tarefas, prazos, agenda, cobranças, portal, chamados e relatórios.',
    },
    {
        title: 'Documentos',
        route: '/documents',
        meaning: 'Repositório privado de arquivos, versões, categorias, validade e visibilidade.',
        actions: ['Enviar documentos.', 'Substituir versões.', 'Visualizar e baixar por rotas seguras.', 'Controlar validade, categoria e sensibilidade.'],
        connection: 'Documentos podem nascer de solicitações documentais e alimentar relatórios de pendências e vencimentos.',
    },
    {
        title: 'Solicitações documentais',
        route: '/document-requests',
        meaning: 'Controle de pedidos de documentos ao cliente, com itens, prazos e revisão.',
        actions: ['Criar solicitações com múltiplos itens.', 'Enviar arquivos para itens.', 'Aprovar ou recusar documentos recebidos.', 'Cancelar solicitações quando necessário.'],
        connection: 'Itens solicitados aparecem no portal do cliente e em indicadores documentais.',
    },
    {
        title: 'Tarefas e modelos',
        route: '/tasks',
        meaning: 'Gestão do trabalho interno com responsáveis, prioridade, prazo e checklist.',
        actions: ['Criar tarefas.', 'Editar status e responsável.', 'Concluir com validação de checklist obrigatório.', 'Gerar tarefas a partir de modelos reutilizáveis.'],
        connection: 'Tarefas se conectam a clientes, agenda, modelos, dashboard e relatórios de produtividade.',
    },
    {
        title: 'Prazos e agenda',
        route: '/deadlines',
        meaning: 'Controle de prazos importantes e eventos de calendário.',
        actions: ['Criar prazos com revisão obrigatória.', 'Solicitar e aprovar revisão.', 'Registrar eventos e reuniões.', 'Gerar tarefas a partir de notas de reunião.'],
        connection: 'Prazos e eventos ajudam a organizar a execução diária e prevenir atrasos.',
    },
    {
        title: 'Financeiro',
        route: '/finance',
        meaning: 'Controle financeiro básico do escritório.',
        actions: ['Criar contas a receber.', 'Registrar pagamentos parciais ou totais.', 'Criar contas a pagar.', 'Categorizar receitas e despesas.', 'Acompanhar inadimplência.'],
        connection: 'Cobranças vinculadas a clientes aparecem em relatórios financeiros e no portal do cliente.',
    },
    {
        title: 'Portal e comunicação',
        route: '/portal',
        meaning: 'Central interna de acessos externos, mensagens e chamados.',
        actions: ['Criar link seguro para cliente.', 'Revogar acesso externo.', 'Registrar mensagens.', 'Criar chamados internos ou a partir de mensagens.'],
        connection: 'O portal externo usa o acesso criado internamente e exibe apenas dados do cliente vinculado ao token.',
    },
    {
        title: 'Relatórios',
        route: '/reports',
        meaning: 'Indicadores gerenciais, filtros, agendamentos planejados e relatórios mensais.',
        actions: ['Filtrar por período e cliente.', 'Analisar produtividade e documentos.', 'Consultar financeiro conforme permissão.', 'Gerar e liberar relatório mensal para o portal.'],
        connection: 'Consolida dados de todos os módulos e fecha o ciclo de prestação de contas ao cliente.',
    },
];

const examples = [
    {
        title: 'Exemplo: documento recusado',
        situation: 'O cliente enviou um arquivo ilegível pelo portal.',
        resolution: ['Abrir a solicitação documental.', 'Localizar o item recebido.', 'Clicar em recusar.', 'Informar um motivo objetivo, como "arquivo ilegível".', 'O item volta a ser tratado como pendência para novo envio.'],
    },
    {
        title: 'Exemplo: cobrança parcialmente paga',
        situation: 'Uma cobrança de R$ 1.000,00 recebeu pagamento de R$ 400,00.',
        resolution: ['Acessar Financeiro.', 'Localizar a conta a receber.', 'Registrar pagamento de R$ 400,00.', 'O status passa para parcial.', 'O saldo de R$ 600,00 continua em aberto e aparece nos indicadores.'],
    },
    {
        title: 'Exemplo: reunião gerando tarefas',
        situation: 'Após uma reunião, surgiram encaminhamentos para a equipe.',
        resolution: ['Abrir Agenda.', 'Selecionar o evento.', 'Registrar notas da reunião.', 'Criar tarefas derivadas com responsável e prazo.', 'Acompanhar execução na tela de Tarefas.'],
    },
];
</script>

<template>
    <Head title="Documentação de Uso" />

    <main class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
                <nav class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600 text-sm font-bold text-white">DF</div>
                        <div>
                            <p class="text-sm font-semibold text-slate-950">Docflow</p>
                            <p class="text-xs text-slate-500">Guia de uso do sistema</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link href="/login" class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-800 hover:bg-slate-50">Entrar</Link>
                        <a href="#rotina" class="inline-flex h-9 items-center rounded-lg bg-blue-600 px-3 text-sm font-semibold text-white hover:bg-blue-700">Ver fluxos</a>
                    </div>
                </nav>

                <section id="visao-geral" class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-end">
                    <div>
                        <div class="flex flex-wrap gap-2">
                            <Badge tone="primary">Aplicação interna</Badge>
                            <Badge tone="secondary">Portal do cliente</Badge>
                            <Badge tone="success">Fluxos operacionais</Badge>
                        </div>
                        <h1 class="mt-5 max-w-4xl text-4xl font-bold tracking-normal text-slate-950 sm:text-5xl">Como usar o Docflow na rotina do escritório</h1>
                        <p class="mt-5 max-w-3xl text-base leading-8 text-slate-600">
                            O Docflow centraliza clientes, documentos, tarefas, prazos, agenda, financeiro, comunicação, portal do cliente e relatórios. Esta documentação explica o fluxo de uso do sistema, o papel de cada página e como os módulos trabalham juntos.
                        </p>
                    </div>
                    <Card title="Leitura recomendada" subtitle="Comece pelos conceitos e depois avance para os fluxos completos.">
                        <div class="grid gap-3 text-sm">
                            <div class="flex justify-between gap-3"><span class="text-slate-500">Primeiro acesso</span><span class="font-semibold text-slate-950">Organização e equipe</span></div>
                            <div class="flex justify-between gap-3"><span class="text-slate-500">Operação diária</span><span class="font-semibold text-slate-950">Dashboard e tarefas</span></div>
                            <div class="flex justify-between gap-3"><span class="text-slate-500">Cliente final</span><span class="font-semibold text-slate-950">Portal externo</span></div>
                        </div>
                    </Card>
                </section>
            </div>
        </header>

        <div class="mx-auto grid max-w-7xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[260px_1fr] lg:px-8">
            <aside class="hidden self-start rounded-lg border border-slate-200 bg-white p-3 lg:sticky lg:top-6 lg:block">
                <a v-for="item in navigation" :key="item.id" :href="`#${item.id}`" class="block rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-blue-700">
                    {{ item.label }}
                </a>
            </aside>

            <div class="grid gap-8">
                <section id="papeis" class="grid gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase text-blue-700">Permissões</p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950">Quem faz o quê</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">As permissões combinam o papel do usuário com a organização ativa e, quando aplicável, o acesso ao cliente.</p>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <Card v-for="role in roles" :key="role.badge">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-base font-semibold text-slate-950">{{ role.name }}</h3>
                                <Badge :tone="role.tone">{{ role.badge }}</Badge>
                            </div>
                            <p class="mt-3 text-sm leading-6 text-slate-600">{{ role.summary }}</p>
                        </Card>
                    </div>
                    <Card title="Regra prática" subtitle="Clientes restritos são visíveis para administradores, gestores, responsáveis do cliente ou membros liberados explicitamente. Perfis somente leitura consultam dados, mas não executam ações de alteração." />
                </section>

                <section id="jornadas" class="grid gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase text-blue-700">Fluxos completos</p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950">Jornadas principais</h2>
                    </div>
                    <div class="grid gap-4 xl:grid-cols-2">
                        <Card v-for="journey in journeys" :key="journey.title" :title="journey.title" :subtitle="journey.description">
                            <ol class="grid gap-3">
                                <li v-for="(step, index) in journey.steps" :key="step" class="flex gap-3 text-sm leading-6 text-slate-700">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-bold text-blue-700">{{ index + 1 }}</span>
                                    <span>{{ step }}</span>
                                </li>
                            </ol>
                        </Card>
                    </div>
                </section>

                <section id="modulos" class="grid gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase text-blue-700">Páginas internas</p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950">O que cada módulo significa</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Cada página tem uma responsabilidade clara, mas todas se conectam ao cliente e à organização ativa.</p>
                    </div>
                    <div class="grid gap-4">
                        <Card v-for="module in modules" :key="module.title">
                            <div class="grid gap-4 lg:grid-cols-[220px_1fr]">
                                <div>
                                    <h3 class="text-base font-semibold text-slate-950">{{ module.title }}</h3>
                                    <p class="mt-1 text-xs font-medium text-blue-700">{{ module.route }}</p>
                                    <p class="mt-3 text-sm leading-6 text-slate-600">{{ module.meaning }}</p>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <p class="text-xs font-semibold uppercase text-slate-500">Ações comuns</p>
                                        <ul class="mt-3 grid gap-2 text-sm text-slate-700">
                                            <li v-for="action in module.actions" :key="action" class="flex gap-2"><span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-blue-500" />{{ action }}</li>
                                        </ul>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-4">
                                        <p class="text-xs font-semibold uppercase text-slate-500">Como se conecta</p>
                                        <p class="mt-3 text-sm leading-6 text-slate-700">{{ module.connection }}</p>
                                    </div>
                                </div>
                            </div>
                        </Card>
                    </div>
                </section>

                <section id="portal" class="grid gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase text-blue-700">Cliente externo</p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950">Portal do cliente</h2>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-3">
                        <Card title="1. Criar acesso" subtitle="A equipe cria um link seguro em /portal para um cliente e contato específico." />
                        <Card title="2. Cliente acompanha" subtitle="O cliente abre /client-portal/{token} e vê apenas suas solicitações, cobranças, chamados, comunicados e relatórios liberados." />
                        <Card title="3. Equipe responde" subtitle="Mensagens e chamados enviados pelo portal ficam vinculados ao cliente e podem ser tratados internamente." />
                    </div>
                    <Card title="Segurança do portal">
                        <div class="grid gap-3 text-sm leading-6 text-slate-700 md:grid-cols-2">
                            <p>O token do portal é exclusivo do cliente vinculado. A equipe pode revogar o acesso quando necessário.</p>
                            <p>O cliente externo não acessa dados internos, observações privadas, outros clientes ou a área administrativa.</p>
                        </div>
                    </Card>
                </section>

                <section id="relatorios" class="grid gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase text-blue-700">Gestão</p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950">Relatórios e prestação de contas</h2>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <Card title="Indicadores internos" subtitle="Visão geral, produtividade, documentos pendentes e financeiro conforme permissão.">
                            <div class="mt-1 flex flex-wrap gap-2">
                                <StatusPill status="active" />
                                <StatusPill status="overdue" />
                                <StatusPill status="completed" />
                                <StatusPill status="paid" />
                            </div>
                        </Card>
                        <Card title="Relatório mensal do cliente" subtitle="Consolida tarefas, documentos, chamados e financeiro visível. Depois de liberado, aparece no portal do cliente." />
                    </div>
                </section>

                <section id="rotina" class="grid gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase text-blue-700">Exemplos práticos</p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950">Como resolver situações comuns</h2>
                    </div>
                    <div class="grid gap-4 xl:grid-cols-3">
                        <Card v-for="example in examples" :key="example.title" :title="example.title" :subtitle="example.situation">
                            <ol class="grid gap-2">
                                <li v-for="(step, index) in example.resolution" :key="step" class="text-sm leading-6 text-slate-700">
                                    <span class="font-semibold text-slate-950">{{ index + 1 }}.</span> {{ step }}
                                </li>
                            </ol>
                        </Card>
                    </div>
                    <Card title="Rotina diária recomendada" subtitle="Uma cadência simples para manter a operação sob controle.">
                        <div class="grid gap-4 md:grid-cols-4">
                            <div class="rounded-lg bg-slate-50 p-4"><p class="text-sm font-semibold text-slate-950">Manhã</p><p class="mt-2 text-sm text-slate-600">Abrir dashboard, revisar alertas e tarefas atrasadas.</p></div>
                            <div class="rounded-lg bg-slate-50 p-4"><p class="text-sm font-semibold text-slate-950">Execução</p><p class="mt-2 text-sm text-slate-600">Atualizar tarefas, prazos, documentos e eventos da agenda.</p></div>
                            <div class="rounded-lg bg-slate-50 p-4"><p class="text-sm font-semibold text-slate-950">Cliente</p><p class="mt-2 text-sm text-slate-600">Responder mensagens, tratar chamados e revisar solicitações.</p></div>
                            <div class="rounded-lg bg-slate-50 p-4"><p class="text-sm font-semibold text-slate-950">Gestão</p><p class="mt-2 text-sm text-slate-600">Consultar relatórios e liberar prestações de contas quando necessário.</p></div>
                        </div>
                    </Card>
                </section>
            </div>
        </div>
    </main>
</template>
