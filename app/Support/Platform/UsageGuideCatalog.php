<?php

namespace App\Support\Platform;

use App\Models\Plan;
use InvalidArgumentException;

class UsageGuideCatalog
{
    /**
     * @return list<array{slug: string, title: string, summary: string, audience: string}>
     */
    public function index(): array
    {
        return array_map(
            fn (array $page): array => [
                'slug' => $page['slug'],
                'title' => $page['title'],
                'summary' => $page['summary'],
                'audience' => $page['audience'],
            ],
            $this->pages(),
        );
    }

    /**
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_column($this->pages(), 'slug');
    }

    /**
     * @return array{
     *     slug: string,
     *     title: string,
     *     summary: string,
     *     audience: string,
     *     sections: list<array<string, mixed>>
     * }
     */
    public function find(string $slug): array
    {
        foreach ($this->pages() as $page) {
            if ($page['slug'] === $slug) {
                return $page;
            }
        }

        throw new InvalidArgumentException("Guia de uso inválido: {$slug}");
    }

    /**
     * @return list<array{
     *     slug: string,
     *     title: string,
     *     summary: string,
     *     audience: string,
     *     sections: list<array<string, mixed>>
     * }>
     */
    public function pages(): array
    {
        return [
            $this->overview(),
            $this->roles(),
            $this->plans(),
            $this->setup(),
            $this->clients(),
            $this->documents(),
            $this->operations(),
            $this->finance(),
            $this->commercial(),
            $this->contracts(),
            $this->automations(),
            $this->portal(),
            $this->dashboardReports(),
            $this->platformOps(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function overview(): array
    {
        return [
            'slug' => 'visao-geral',
            'title' => 'Visão geral do Docflow',
            'summary' => 'O que o produto cobre, quem usa cada superfície e como os fluxos se conectam.',
            'audience' => 'Platform + onboarding comercial',
            'sections' => [
                [
                    'heading' => 'O produto em três superfícies',
                    'body' => 'O Docflow tem três contextos distintos. Misturá-los na explicação ao cliente gera expectativa errada.',
                    'bullets' => [
                        'App do escritório (`/dashboard` e módulos): operação multi-usuário da organização ativa.',
                        'Portal do cliente (`/client-portal/{token}`): superfície externa escopada a um único cliente.',
                        'Platform (`/platform`): administração cross-tenant (planos, orgs, faturas, este guia).',
                    ],
                ],
                [
                    'heading' => 'Conceitos centrais',
                    'bullets' => [
                        'Organização (tenant): unidade de isolamento de dados, plano e assinatura.',
                        'Membership: vínculo usuário ↔ organização com papel (role) e status.',
                        'Cliente: eixo operacional (documentos, tarefas, contratos, cobranças, portal).',
                        'Lead: oportunidade comercial (feature `crm`, Profissional+).',
                        'Contrato / serviço: escopo e receita recorrente do escritório com o cliente.',
                    ],
                ],
                [
                    'heading' => 'Mapa rápido de páginas internas',
                    'pages' => [
                        ['path' => '/dashboard', 'name' => 'Dashboard', 'notes' => 'Resultado do período + alertas operacionais'],
                        ['path' => '/organizations', 'name' => 'Organizações', 'notes' => 'Troca e gestão da org ativa'],
                        ['path' => '/team', 'name' => 'Equipe', 'notes' => 'Membros, convites e papéis'],
                        ['path' => '/leads', 'name' => 'CRM', 'notes' => 'Feature `crm`'],
                        ['path' => '/clients', 'name' => 'Clientes', 'notes' => 'Hub por cliente'],
                        ['path' => '/service-types', 'name' => 'Serviços', 'notes' => 'Catálogo (admin/manager)'],
                        ['path' => '/contracts', 'name' => 'Contratos', 'notes' => 'Vigência e valores'],
                        ['path' => '/automations', 'name' => 'Automações', 'notes' => 'Feature `automations`'],
                        ['path' => '/documents', 'name' => 'Documentos', 'notes' => 'Repositório versionado'],
                        ['path' => '/document-requests', 'name' => 'Solicitações', 'notes' => 'Pedidos ao cliente'],
                        ['path' => '/tasks', 'name' => 'Tarefas', 'notes' => 'Execução interna'],
                        ['path' => '/finance', 'name' => 'Financeiro', 'notes' => 'Roles admin/manager/finance'],
                        ['path' => '/portal', 'name' => 'Portal', 'notes' => 'Acessos externos (feature `portal`)'],
                        ['path' => '/reports', 'name' => 'Relatórios', 'notes' => 'Indicadores e mensal'],
                        ['path' => '/audit', 'name' => 'Auditoria', 'notes' => 'Feature `audit`'],
                    ],
                ],
                [
                    'heading' => 'Fluxo mestre do valor',
                    'steps' => [
                        'Lead entra no CRM e avança no funil.',
                        'Conversão gera (ou associa) cliente e pode disparar automações.',
                        'Serviços/contratos amarram escopo e MRR.',
                        'Operação: documentos, tarefas, prazos, agenda.',
                        'Financeiro registra cobranças e recebimentos.',
                        'Portal entrega transparência ao cliente final.',
                        'Dashboard/relatórios mostram resultado e risco.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function roles(): array
    {
        return [
            'slug' => 'papeis-e-permissoes',
            'title' => 'Papéis e permissões',
            'summary' => 'Regras de role, acesso a cliente restrito e o que cada perfil pode ver.',
            'audience' => 'CS e implantação',
            'sections' => [
                [
                    'heading' => 'Papéis do escritório',
                    'bullets' => [
                        'Administrador: configuração completa da org, equipe, todos os módulos.',
                        'Gestor: operação ampla, clientes restritos, financeiro e relatórios.',
                        'Profissional: atua nos clientes sob sua responsabilidade / política de acesso.',
                        'Assistente: apoio operacional sem financeiro.',
                        'Financeiro: cobranças, pagamentos, despesas e KPIs financeiros.',
                        'Somente leitura: consulta sem mutações.',
                    ],
                ],
                [
                    'heading' => 'Regras de acesso a cliente',
                    'rules' => [
                        'Admin e manager veem todos os clientes da organização.',
                        'Demais papéis: clientes com `access_policy = all_members`, ou onde são responsáveis, ou liberados em `access_members`.',
                        'Cliente restrito sem vínculo: 403 / fora de listagens.',
                        'Documentos confidenciais: em geral só admin/manager (DocumentPolicy).',
                    ],
                ],
                [
                    'heading' => 'Gates de módulo vs papel',
                    'bullets' => [
                        'Financeiro no dashboard/relatórios: admin, manager, finance.',
                        'CRM (`canViewCrm`): admin, manager, professional, assistant + feature `crm`.',
                        'Gestão de CRM/onboarding templates: admin, manager, professional (+ feature).',
                        'Automações: admin/manager + feature `automations`.',
                        'Contratos listagem: bloqueado para readonly.',
                        'Platform: flag `users.is_platform_admin` (não é papel Spatie do tenant).',
                    ],
                ],
                [
                    'heading' => 'Organização ativa',
                    'body' => 'Quase toda rota do app exige membership ativa na sessão (`active_organization_id`). Sem org selecionada, o usuário é redirecionado para `/organizations`.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function plans(): array
    {
        $unlimited = Plan::LIMIT_UNLIMITED;

        return [
            'slug' => 'planos-limites-features',
            'title' => 'Planos, limites e features',
            'summary' => 'Tabela dos planos seedados, o que cada feature libera e como overrides funcionam.',
            'audience' => 'Comercial e suporte',
            'sections' => [
                [
                    'heading' => 'Planos padrão (PlanSeeder)',
                    'body' => 'Valores podem ser alterados em `/platform/plans`. Abaixo está o seed de referência.',
                    'tables' => [
                        [
                            'title' => 'Limites',
                            'headers' => ['Plano', 'Membros', 'Clientes', 'Storage', 'Acessos portal'],
                            'rows' => [
                                ['Essencial', '3', '50', '2 GB', '10'],
                                ['Profissional', '15', '300', '20 GB', '100'],
                                ['Escritório', '50', $unlimited === -1 ? 'Ilimitado' : (string) $unlimited, '100 GB', 'Ilimitado'],
                            ],
                        ],
                        [
                            'title' => 'Features',
                            'headers' => ['Feature', 'Essencial', 'Profissional', 'Escritório'],
                            'rows' => [
                                ['Portal do cliente (`portal`)', 'Não', 'Sim', 'Sim'],
                                ['Financeiro avançado (`finance_advanced`)', 'Não', 'Sim', 'Sim'],
                                ['Agendamento de relatórios (`reports_scheduling`)', 'Não', 'Sim', 'Sim'],
                                ['Auditoria (`audit`)', 'Não', 'Não', 'Sim'],
                                ['Automações (`automations`)', 'Não', 'Sim', 'Sim'],
                                ['CRM e onboarding (`crm`)', 'Não', 'Sim', 'Sim'],
                            ],
                        ],
                    ],
                ],
                [
                    'heading' => 'Como o enforcement funciona',
                    'rules' => [
                        '`PlanLimitChecker::assertWithinLimit` bloqueia criação além do teto (membros, clientes, storage, portal accesses).',
                        '`assertFeature` redireciona/bloqueia módulos sem feature (CRM, automações, portal, auditoria…).',
                        'Overrides por organização em `/platform/organizations/{id}` alteram limite/feature sem mudar o plano base.',
                        'Assinatura tem trial (padrão 14 dias), grace (7 dias) e status (`trialing`, `active`, `past_due`, `paused`, `canceled`).',
                        'Org suspensa na platform perde acesso operacional do tenant.',
                    ],
                ],
                [
                    'heading' => 'Mensagens ao usuário do escritório',
                    'bullets' => [
                        'Banner de uso do plano (`PlanUsageBanner`) alerta proximidade de limite.',
                        'Banner de assinatura informa trial/past_due.',
                        'Tentativa de usar feature indisponível costuma ir para upgrade (`/organizations/plan`).',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function setup(): array
    {
        return [
            'slug' => 'implantacao-escritorio',
            'title' => 'Implantação do escritório',
            'summary' => 'Checklist do primeiro uso após signup ou criação manual do tenant.',
            'audience' => 'CS / onboarding',
            'sections' => [
                [
                    'heading' => 'Fluxo recomendado',
                    'steps' => [
                        'Criar conta e organização (ou receber convite).',
                        'Selecionar organização ativa em `/organizations`.',
                        'Convidar equipe em `/team` com papéis corretos.',
                        'Cadastrar categorias documentais e financeiras necessárias.',
                        'Criar modelos de tarefa (`/task-templates`) para onboarding.',
                        'Se Profissional+: configurar CRM, templates de onboarding e 1–2 automações preset.',
                        'Cadastrar clientes prioritários e, se houver, contratos.',
                        'Abrir `/dashboard` e validar alertas/KPIs.',
                    ],
                ],
                [
                    'heading' => 'Páginas da implantação',
                    'pages' => [
                        ['path' => '/organizations', 'name' => 'Organizações', 'notes' => 'Criar/selecionar tenant'],
                        ['path' => '/organizations/plan', 'name' => 'Plano', 'notes' => 'Self-service de plano/assinatura'],
                        ['path' => '/team', 'name' => 'Equipe', 'notes' => 'Convites e papéis'],
                        ['path' => '/task-templates', 'name' => 'Modelos de tarefa', 'notes' => 'Base de automações e onboarding'],
                        ['path' => '/onboarding-templates', 'name' => 'Onboarding', 'notes' => 'Checklists CRM (Profissional+)'],
                    ],
                ],
                [
                    'heading' => 'Erros comuns',
                    'bullets' => [
                        'Usuário sem membership ativa: não enxerga dados.',
                        'Assistente tentando financeiro: KPIs/alertas ocultos.',
                        'Essencial tentando CRM/automações: upgrade obrigatório.',
                        'Cliente restrito sem responsável: profissional não vê o caso.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clients(): array
    {
        return [
            'slug' => 'clientes',
            'title' => 'Clientes e hub comercial',
            'summary' => 'Cadastro, política de acesso, ficha do cliente e abas operacionais.',
            'audience' => 'Operação',
            'sections' => [
                [
                    'heading' => 'Páginas',
                    'pages' => [
                        ['path' => '/clients', 'name' => 'Listagem', 'notes' => 'Filtros por status, risco, tags'],
                        ['path' => '/clients/{id}', 'name' => 'Hub do cliente', 'notes' => 'Visão unificada + abas'],
                    ],
                ],
                [
                    'heading' => 'Fluxo de cadastro',
                    'steps' => [
                        'Criar PF/PJ com dados mínimos.',
                        'Definir responsável principal e contatos (idealmente um contato principal).',
                        'Ajustar status, prioridade, risco e etiquetas.',
                        'Escolher `access_policy` (todos os membros vs restrito).',
                        'Se restrito, liberar membros adicionais conforme necessário.',
                        'Opcional: criar solicitação documental e tarefas de onboarding.',
                    ],
                ],
                [
                    'heading' => 'Regras',
                    'rules' => [
                        'Limite `max_clients` do plano é validado na criação.',
                        'Clientes sem contato principal aparecem como pendência estrutural no dashboard.',
                        'Status inadimplente pode ser derivado da política financeira (atraso prolongado).',
                        'Hub do cliente agrega serviços, contratos, comercial (CRM) e operação conforme permissão.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documents(): array
    {
        return [
            'slug' => 'documentos',
            'title' => 'Documentos e solicitações',
            'summary' => 'Repositório interno, pedidos ao cliente, aprovação e validade.',
            'audience' => 'Operação',
            'sections' => [
                [
                    'heading' => 'Páginas',
                    'pages' => [
                        ['path' => '/documents', 'name' => 'Documentos', 'notes' => 'Upload, versão, validade, visibilidade'],
                        ['path' => '/document-requests', 'name' => 'Solicitações', 'notes' => 'Itens, prazos, aprovação/recusa'],
                    ],
                ],
                [
                    'heading' => 'Fluxo de solicitação documental',
                    'steps' => [
                        'Criar solicitação vinculada ao cliente.',
                        'Adicionar itens com instruções e `due_at`.',
                        'Cliente envia pelo portal ou equipe anexa internamente.',
                        'Revisar: aprovar ou recusar com motivo.',
                        'Item recusado volta como pendência.',
                        'Dashboard alerta vencidos e a vencer em 7 dias.',
                    ],
                ],
                [
                    'heading' => 'Regras',
                    'rules' => [
                        'Storage conta no limite `max_storage_mb`.',
                        'Visibilidade confidencial restringe quem pode ver o arquivo.',
                        'Documentos substituídos/rejeitados/expirados não devem alimentar automações de vencimento.',
                        'Download/visualização usam rotas autorizadas (não URL pública aberta).',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function operations(): array
    {
        return [
            'slug' => 'operacao',
            'title' => 'Tarefas, prazos e agenda',
            'summary' => 'Execução diária da equipe e modelos reutilizáveis.',
            'audience' => 'Operação',
            'sections' => [
                [
                    'heading' => 'Páginas',
                    'pages' => [
                        ['path' => '/tasks', 'name' => 'Tarefas', 'notes' => 'Status, responsável, checklist'],
                        ['path' => '/task-templates', 'name' => 'Modelos', 'notes' => 'Base para onboarding e automações'],
                        ['path' => '/deadlines', 'name' => 'Prazos', 'notes' => 'Revisão obrigatória em alguns fluxos'],
                        ['path' => '/calendar', 'name' => 'Agenda', 'notes' => 'Eventos, reuniões, notas → tarefas'],
                    ],
                ],
                [
                    'heading' => 'Fluxos típicos',
                    'bullets' => [
                        'Criar tarefa avulsa ou a partir de modelo.',
                        'Concluir só com checklist obrigatório completo.',
                        'Prazo atrasado aparece no dashboard e alertas.',
                        'Após reunião, registrar notas e gerar tarefas derivadas.',
                        'Automações podem criar tarefas de template no `client.created`.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function finance(): array
    {
        return [
            'slug' => 'financeiro',
            'title' => 'Financeiro do escritório',
            'summary' => 'Cobranças, recorrências (manuais ou do contrato), pagamentos e o que aparece no dashboard.',
            'audience' => 'Financeiro / gestor',
            'sections' => [
                [
                    'heading' => 'Página',
                    'pages' => [
                        ['path' => '/finance', 'name' => 'Financeiro', 'notes' => 'Receivables, recorrências, payments, payables, categorias'],
                        ['path' => '/contracts/{id}', 'name' => 'Contrato', 'notes' => 'Pode gerar/pausar a recorrência ligada'],
                    ],
                ],
                [
                    'heading' => 'Fluxos',
                    'steps' => [
                        'Criar cobrança avulsa (receivable) vinculada a cliente.',
                        'Ou cadastrar recorrência em `/finance`, ou marcar “Gerar mensalidade no financeiro” no contrato mensal/anual ativo.',
                        'O scheduler `finance:generate-recurring-receivables` materializa as faturas no vencimento — o cadastro da recorrência não emite a primeira na hora.',
                        'Registrar pagamento total ou parcial.',
                        'Status parcial mantém saldo em aberto nos indicadores.',
                        'Criar despesa (payable) e marcar como paga.',
                        'Dashboard (roles financeiras): recebido no período, aberto, vencido, saldo líquido.',
                    ],
                ],
                [
                    'heading' => 'Recorrências',
                    'steps' => [
                        'Criar à mão em `/finance` (botão Recorrência) com cliente, valor e dia de vencimento.',
                        'Contrato mensal/anual ativo: admin/gestor marca o checkbox no criar ou no renovar (se ainda não houver recorrência).',
                        'Recorrência ligada ao contrato aparece no card do detalhe e na listagem de `/finance`.',
                        'Cancelar o contrato pausa a recorrência (`is_active = false`); também dá para pausar no financeiro.',
                        'Gerar agora na listagem ou esperar o scheduler diário.',
                    ],
                    'rules' => [
                        'Recorrência do escritório não é fatura SaaS (`/platform/invoices` / Asaas da assinatura Docflow).',
                        'Contrato único (`once`) ou valor zero não gera recorrência, mesmo com a flag.',
                        'Uma recorrência por contrato (`contract_id` único); não duplica ao salvar de novo.',
                    ],
                ],
                [
                    'heading' => 'Regras',
                    'rules' => [
                        'Assistente e readonly não veem centavos no dashboard.',
                        'Inadimplência/delinquência usa política de dias em atraso (config `docflow.finance`).',
                        'Feature `finance_advanced` habilita capacidades avançadas do plano Profissional+.',
                        'Cobranças vencidas podem disparar automação `receivable.overdue` (Profissional+).',
                        'Portal pode exibir cobranças do cliente conforme configuração do acesso.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commercial(): array
    {
        return [
            'slug' => 'crm-onboarding',
            'title' => 'CRM e onboarding',
            'summary' => 'Funil de leads, atividades, propostas, conversão e checklists — só Profissional+.',
            'audience' => 'Comercial',
            'sections' => [
                [
                    'heading' => 'Disponibilidade',
                    'rules' => [
                        'Feature `crm` obrigatória (Essencial: bloqueado).',
                        'Roles: admin, manager, professional, assistant (visualização); gestão mais restrita a admin/manager/professional.',
                        'Finance/readonly não acessam CRM.',
                    ],
                ],
                [
                    'heading' => 'Páginas',
                    'pages' => [
                        ['path' => '/leads', 'name' => 'Board de leads', 'notes' => 'Stages do funil'],
                        ['path' => '/leads/{id}', 'name' => 'Detalhe do lead', 'notes' => 'Atividades, propostas, conversão'],
                        ['path' => '/onboarding-templates', 'name' => 'Templates de onboarding', 'notes' => 'Admin/manager'],
                    ],
                ],
                [
                    'heading' => 'Fluxo lead → cliente',
                    'steps' => [
                        'Criar lead com valor estimado e origem.',
                        'Mover stages (new → … → negotiation).',
                        'Registrar atividades e propostas (`draft/sent/accepted/rejected`).',
                        'Converter para cliente novo ou existente (lock evita corrida).',
                        'Stage `won` sem conversão é bloqueado.',
                        'Conversão pode disparar automação `client.created` e aplicar onboarding.',
                    ],
                ],
                [
                    'heading' => 'Dashboard comercial',
                    'bullets' => [
                        'Pipeline aberto = soma de `estimated_value_cents` em stages abertos.',
                        'Ganho no período = leads convertidos + propostas aceitas (sem double count).',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contracts(): array
    {
        return [
            'slug' => 'servicos-contratos',
            'title' => 'Serviços e contratos',
            'summary' => 'Catálogo de serviços, vigência, renovação, MRR estimado e mensalidade no financeiro.',
            'audience' => 'Operação / comercial',
            'sections' => [
                [
                    'heading' => 'Páginas',
                    'pages' => [
                        ['path' => '/service-types', 'name' => 'Tipos de serviço', 'notes' => 'Catálogo da org (admin/manager)'],
                        ['path' => '/contracts', 'name' => 'Contratos', 'notes' => 'Listagem escopada por acesso a cliente'],
                        ['path' => '/contracts/{id}', 'name' => 'Detalhe', 'notes' => 'Renovação, cancelamento e cobrança recorrente'],
                        ['path' => '/clients/{id}', 'name' => 'Hub cliente', 'notes' => 'Abas Serviços e Contratos'],
                        ['path' => '/finance', 'name' => 'Financeiro', 'notes' => 'Recorrência gerada pelo contrato'],
                    ],
                ],
                [
                    'heading' => 'Fluxo',
                    'steps' => [
                        'Cadastrar tipos de serviço com defaults (valor/recorrência).',
                        'Vincular serviços ao cliente.',
                        'Criar contrato com código, valor, intervalo (`month|year|once`), vigência e escopo.',
                        'Associar serviços ao contrato quando fizer sentido.',
                        'Se mensal/anual e status ativo, admin/gestor pode marcar “Gerar mensalidade no financeiro”.',
                        'Sem a flag, o contrato não cria cobrança — o escritório lança à mão em `/finance`.',
                        'Renovar ou cancelar com motivo (cancelar pausa a recorrência ligada).',
                        'Dashboard mostra MRR estimado (pelos contratos ativos) e valor em risco (30 dias).',
                    ],
                ],
                [
                    'heading' => 'Contrato e mensalidade',
                    'steps' => [
                        'O checkbox só aparece para admin/gestor, contrato ativo, intervalo mensal ou anual.',
                        'Marcar cria uma recorrência ligada ao contrato (mesmo cliente e valor).',
                        'Intervalo único (`once`) ignora a flag.',
                        'O detalhe mostra o card “Cobrança recorrente” (ativa/pausada) com atalho para `/finance`.',
                        'Renovar: se já existe recorrência, atualiza término/valor e reativa; se não existe e a flag estiver marcada, cria.',
                        'Cancelar o contrato pausa a recorrência. Não apaga o histórico no financeiro.',
                    ],
                    'rules' => [
                        'Uma recorrência por contrato; salvar de novo não duplica.',
                        'A flag não altera o MRR do dashboard — MRR continua estimado pelos contratos ativos, mesmo sem mensalidade gerada.',
                        'Recorrência do contrato é financeiro do escritório, não fatura SaaS em `/platform/invoices`.',
                        'A primeira fatura nasce no scheduler `finance:generate-recurring-receivables`, não na hora do cadastro.',
                        'Assistente/profissional não veem o checkbox; enviar a flag no POST não cria recorrência.',
                    ],
                ],
                [
                    'heading' => 'Regras',
                    'rules' => [
                        'Readonly não lista contratos.',
                        'Listagens respeitam `clientQuery` (cliente restrito).',
                        'MRR: mensal = valor; anual = valor/12; único não entra no MRR.',
                        'Contratos a vencer alimentam alerta e automação `contract.expiring`.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function automations(): array
    {
        return [
            'slug' => 'automacoes',
            'title' => 'Automações',
            'summary' => 'Presets, gatilhos, ações, logs idempotentes e limites de plano.',
            'audience' => 'Admin do escritório',
            'sections' => [
                [
                    'heading' => 'Disponibilidade',
                    'rules' => [
                        'Feature `automations` (Profissional+).',
                        'Somente admin/manager criam e pausam regras.',
                        'Após downgrade, runner falha fechado (não executa).',
                    ],
                ],
                [
                    'heading' => 'Páginas',
                    'pages' => [
                        ['path' => '/automations', 'name' => 'Regras', 'notes' => 'Criar a partir de presets'],
                        ['path' => '/automations/{id}', 'name' => 'Detalhe', 'notes' => 'Últimas execuções / pausar'],
                    ],
                ],
                [
                    'heading' => 'Gatilhos MVP',
                    'bullets' => [
                        '`client.created` — cliente novo (inclui conversão CRM).',
                        '`lead.stage_changed` — mudança de stage.',
                        '`document.expiring` — scheduler diário.',
                        '`contract.expiring` — scheduler diário.',
                        '`receivable.overdue` — scheduler diário.',
                    ],
                ],
                [
                    'heading' => 'Ações MVP',
                    'bullets' => [
                        'Criar tarefas a partir de template.',
                        'Criar solicitação documental.',
                        'Notificar membros da org (InternalReminder tipo automation).',
                    ],
                ],
                [
                    'heading' => 'Idempotência e segurança',
                    'rules' => [
                        '`automation_logs` unique por `organization_id + dedupe_key`.',
                        'Notificações respeitam policy de `view` do subject (ex.: doc confidencial).',
                        'Reexecução reabre lembrete (`read_at` limpo) sem violar unique de reminders.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function portal(): array
    {
        return [
            'slug' => 'portal-do-cliente',
            'title' => 'Portal do cliente (fluxo completo)',
            'summary' => 'Como o cliente final usa o sistema: acesso, documentos, chamados, mensagens, finanças e limites.',
            'audience' => 'CS + treinamento do escritório',
            'sections' => [
                [
                    'heading' => 'Disponibilidade',
                    'rules' => [
                        'Feature `portal` necessária para criar acessos (Profissional+ no seed).',
                        'Limite `max_portal_accesses` por organização.',
                        'Token exclusivo por acesso; revogável a qualquer momento.',
                    ],
                ],
                [
                    'heading' => 'Páginas internas (escritório)',
                    'pages' => [
                        ['path' => '/portal', 'name' => 'Gestão de acessos', 'notes' => 'Criar/revogar links'],
                        ['path' => '/message-templates', 'name' => 'Modelos de mensagem', 'notes' => 'Comunicação padronizada'],
                        ['path' => '/announcements', 'name' => 'Comunicados', 'notes' => 'Avisos ao portal'],
                    ],
                ],
                [
                    'heading' => 'Páginas do cliente (`/client-portal`)',
                    'pages' => [
                        ['path' => '/client-portal', 'name' => 'Início', 'notes' => 'Resumo de pendências'],
                        ['path' => '/client-portal/documents', 'name' => 'Documentos', 'notes' => 'Itens solicitados / envio'],
                        ['path' => '/client-portal/tickets', 'name' => 'Chamados', 'notes' => 'Abertura, respostas, avaliação'],
                        ['path' => '/client-portal/messages', 'name' => 'Mensagens', 'notes' => 'Canal com o escritório'],
                        ['path' => '/client-portal/more', 'name' => 'Mais', 'notes' => 'Reuniões, perfil, cobranças/relatórios conforme liberado'],
                    ],
                ],
                [
                    'heading' => 'Fluxo ponta a ponta',
                    'steps' => [
                        'Escritório cria acesso em `/portal` para cliente + contato.',
                        'Cliente abre o link com token (sem login do app interno).',
                        'Cliente vê somente dados do próprio cliente.',
                        'Envia documentos pedidos → equipe aprova/recusa.',
                        'Abre chamado ou mensagem → equipe responde internamente.',
                        'Confirma reunião / atualiza perfil quando habilitado.',
                        'Consulta cobranças e relatórios mensais liberados.',
                        'Escritório pode revogar o token encerrando o acesso.',
                    ],
                ],
                [
                    'heading' => 'O que o cliente NÃO vê',
                    'bullets' => [
                        'Outros clientes da organização.',
                        'Observações internas, auditoria, automações, CRM, equipe.',
                        'Documentos confidenciais / não liberados.',
                        'Área `/platform` ou app interno completo.',
                    ],
                ],
                [
                    'heading' => 'Regras de segurança',
                    'rules' => [
                        'Token amarra organização + cliente; falha fechada se revogado/expirado.',
                        'Uploads passam por validação e contam no storage da org.',
                        'Notificações internas à equipe usam InternalReminder / e-mail em fila.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardReports(): array
    {
        return [
            'slug' => 'dashboard-e-relatorios',
            'title' => 'Dashboard e relatórios',
            'summary' => 'Como ler o painel de resultado e os relatórios gerenciais.',
            'audience' => 'Gestão',
            'sections' => [
                [
                    'heading' => 'Dashboard (`/dashboard`)',
                    'bullets' => [
                        'Hero financeiro: recebido (delta MTD), aberto, vencido, saldo líquido.',
                        'Contratos: MRR estimado e valor em risco 30d.',
                        'Comercial (CRM): pipeline e ganho no período.',
                        'Alertas operacionais clicáveis (tarefas, docs, cobranças, contratos).',
                        'Seção Operação: KPIs de backlog.',
                        'Filtro de período: mês (MTD), 7 dias ou custom.',
                    ],
                ],
                [
                    'heading' => 'Relatórios (`/reports`)',
                    'bullets' => [
                        'Visão geral, produtividade, documentos, financeiro (se permitido).',
                        'Relatório mensal por cliente com liberação ao portal.',
                        'Agendamento de relatórios depende da feature `reports_scheduling`.',
                        'Exportações controladas por permissão.',
                    ],
                ],
                [
                    'heading' => 'Rotina diária sugerida',
                    'steps' => [
                        'Abrir dashboard e tratar alertas vermelhos.',
                        'Olhar valor em risco de contratos e cobranças vencidas.',
                        'Executar tarefas/documentos do dia.',
                        'Responder portal (mensagens/chamados).',
                        'Fechar com relatório ou follow-up comercial se Profissional+.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function platformOps(): array
    {
        return [
            'slug' => 'platform-admin',
            'title' => 'Operações na Platform',
            'summary' => 'O que o admin da plataforma faz em `/platform` no dia a dia.',
            'audience' => 'Platform admin',
            'sections' => [
                [
                    'heading' => 'Páginas',
                    'pages' => [
                        ['path' => '/platform', 'name' => 'Dashboard', 'notes' => 'MRR, trials, past_due, faturas'],
                        ['path' => '/platform/organizations', 'name' => 'Organizações', 'notes' => 'Busca, ficha, notas, suspender'],
                        ['path' => '/platform/plans', 'name' => 'Planos', 'notes' => 'CRUD de limites/features'],
                        ['path' => '/platform/invoices', 'name' => 'Faturas', 'notes' => 'Marcar paga / void'],
                        ['path' => '/platform/guides', 'name' => 'Guia de uso', 'notes' => 'Esta documentação'],
                    ],
                ],
                [
                    'heading' => 'Fluxos críticos',
                    'steps' => [
                        'Investigar tenant: abrir ficha da organização.',
                        'Ajustar plano ou criar override de limite/feature.',
                        'Estender trial / alterar status de assinatura.',
                        'Suspender org inadimplente (com motivo) — audita ação.',
                        'Reativar após regularização.',
                        'Marcar fatura como paga ou anular.',
                    ],
                ],
                [
                    'heading' => 'Regras',
                    'rules' => [
                        'Acesso somente com `is_platform_admin`.',
                        'Ações sensíveis geram `platform_audit_logs`.',
                        'Billing pode ser manual ou Asaas (config `docflow.billing`).',
                        'Nunca operar dados de cliente final pela platform — use o app do tenant.',
                    ],
                ],
            ],
        ];
    }
}
