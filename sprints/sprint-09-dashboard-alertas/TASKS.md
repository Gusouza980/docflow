# Sprint 09 — Dashboard e alertas operacionais

## Objetivo

Completar o painel gerencial para uso diário: KPIs operacionais, documentais e financeiros com **alertas acionáveis** (link direto ao recurso), respeitando permissões e escopo por organização.

## Referências

- `sprints/sprint-07-relatorios-indicadores/TASKS.md` (seção Painel e alertas — incompleta na UI)
- `app/Reports/ReportMetrics.php`
- `app/Http/Controllers/Web/DashboardController.php`
- `resources/js/Pages/Dashboard/Index.vue`

## Pré-requisitos

- Sprints 03–07 implementadas (tarefas, documentos, financeiro básico, chamados).
- Financeiro com recorrência e inadimplência (Sprint 05 estendida).

## Escopo funcional

- Exibir cards de KPI: tarefas abertas/atrasadas/concluídas no período, documentos pendentes/vencidos/próximos, cobranças em aberto/vencidas (se permissão financeira).
- Exibir lista de **alertas críticos** com URL, severidade e contagem.
- Permitir filtro de período no dashboard (mês atual por padrão).
- Links de alerta levam à listagem filtrada correta (`/tasks`, `/document-requests`, `/finance`, `/clients/{id}`).
- Usuário sem financeiro **não vê valores** nem alertas financeiros.

## Tarefas técnicas

### Backend — métricas e alertas

- [ ] Estender `ReportMetrics::alerts()` com:
  - cobranças vencidas (open/partial + `due_at` passado);
  - cobranças a vencer em 7 dias;
  - chamados aguardando equipe (`waiting_client` invertido: tickets sem resposta interna há X dias — opcional fase 2);
  - clientes inadimplentes (`Client::STATUS_DELINQUENT`) ou alto risco.
- [ ] Padronizar formato de alerta: `{ type, severity, label, count, href, filter? }`.
- [ ] Adicionar `ReportMetrics::dashboard()` ou método dedicado `BuildsDashboardPayload` para não inflar controller.
- [ ] Passar `can_access_finance` no payload do dashboard.
- [ ] Incluir KPI financeiro em `metrics` somente quando `canAccessFinance()`.
- [ ] Suportar query `?period=month|week|custom` + `start_date` / `end_date` no dashboard.

### Frontend — Dashboard/Index.vue

- [ ] Substituir cards estáticos de clientes por grid de KPIs operacionais (tarefas, documentos, financeiro condicional).
- [ ] Renderizar alertas como lista clicável (`Link` ou `router.visit`) com ícone/cor por severidade.
- [ ] Adicionar seletor de período (mês atual / últimos 7 dias / custom).
- [ ] Skeleton/empty state quando organização não tem dados.
- [ ] Manter bloco “Pendências estruturais” (clientes sem contato principal).

### Permissões e performance

- [ ] Reutilizar `clientQuery()` e filtros de membro restrito em todos os KPIs.
- [ ] Evitar N+1: agregações com `count()` / subqueries, não loops.
- [ ] Cache opcional de overview por organização (60s) — só se necessário após profiling.

### Testes

- [ ] Feature: dashboard retorna alertas de tarefas e documentos atrasados.
- [ ] Feature: usuário financeiro vê KPI de inadimplência; assistente não vê.
- [ ] Feature: alertas contêm `href` válido (route name).
- [ ] Feature: filtro de período altera `completed_tasks`.

## Endpoints

- `GET /dashboard` (Inertia) — props estendidas, sem nova rota API obrigatória.

## Condições de aceite

- Dashboard exibe pelo menos 6 KPIs úteis para gestão diária.
- Todo alerta listado possui link funcional para a tela correta.
- Alertas financeiros ocultos para roles sem acesso financeiro.
- Métricas respeitam organização ativa e restrição de clientes do membro.
- Testes feature cobrem permissão financeira e presença de alertas.

## Fora do escopo

- Gráficos/charts complexos ou biblioteca de BI.
- Widgets configuráveis por usuário.
- Notificações push no dashboard (já existe sino separado).

## Ordem sugerida de implementação

1. Estender `ReportMetrics::alerts()` + testes unitários/feature.
2. Atualizar `DashboardController` payload.
3. Refatorar `Dashboard/Index.vue` (KPIs + alertas clicáveis).
4. Período customizado + polish visual.
