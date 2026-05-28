# Sprint 04 — Tarefas, Prazos e Agenda

## Objetivo

Implementar gestão operacional de trabalho: tarefas, responsáveis, checklists, modelos, prazos importantes, agenda, reuniões, lembretes e visão de carga de trabalho.

## Referências

- `docs/briefing_app_gestao_escritorios.md`
- `docs/documento_tecnico_app_gestao_escritorios.md`
- `docs/casos_de_uso_app_gestao_escritorios.md`
- Casos de uso: UC-051 a UC-064, UC-101, UC-113, UC-114.

## Escopo funcional

- Criar tarefa.
- Atribuir e reatribuir tarefa.
- Atualizar status.
- Concluir tarefa.
- Criar checklist.
- Criar modelo de tarefa.
- Criar tarefas a partir de modelo.
- Criar prazo importante.
- Confirmar cumprimento de prazo.
- Revisar prazo antes de conclusão.
- Visualizar agenda.
- Agendar reunião.
- Registrar ata ou resumo de reunião.
- Receber lembretes.

## Tarefas técnicas

### Modelagem e migrations

- Criar tabela `tasks`.
- Criar tabela `task_checklist_items`.
- Criar tabela `task_templates`.
- Criar tabela `task_template_items`.
- Criar tabela `deadlines`.
- Criar tabela `calendar_events`.
- Criar tabela de participantes de eventos, se necessário.
- Criar tabela de comentários de tarefas ou usar estrutura comum de comentários.
- Incluir `organization_id`, `client_id`, `assigned_to_user_id`, status, prioridade, prazo e datas de conclusão.
- Adicionar índices por organização, cliente, responsável, status, prioridade, prazo e datas.

### Tarefas

- Implementar criação de tarefa com título, descrição, cliente, serviço opcional, responsável, prazo e prioridade.
- Impedir tarefa sem prazo quando regra do produto exigir.
- Implementar atualização de status com transições válidas.
- Implementar conclusão com verificação de checklist obrigatório.
- Implementar comentários e histórico básico de alterações.
- Implementar anexos por relação com documentos quando aplicável.

### Checklists e modelos

- Implementar checklist dentro da tarefa.
- Implementar progresso por itens concluídos.
- Implementar modelos de tarefas com prazos relativos.
- Implementar criação de tarefas a partir de modelo.
- Registrar origem da tarefa quando criada por modelo.

### Prazos importantes

- Implementar cadastro de prazo importante separado de tarefa.
- Permitir tipo, urgência, responsável, cliente e revisão obrigatória.
- Implementar cumprimento de prazo.
- Implementar fluxo de revisão: solicitar revisão, aprovar, solicitar ajuste.
- Garantir alertas e status derivados de vencimento.

### Agenda e reuniões

- Implementar eventos de agenda.
- Permitir reuniões com cliente, eventos internos, vencimentos e audiências futuras.
- Permitir participantes internos e externos.
- Implementar confirmação de reunião em estrutura preparada para portal.
- Implementar registro de ata/resumo.
- Permitir gerar tarefas a partir da reunião.

### Lembretes e notificações internas

- Criar notificações internas para atribuição de tarefa, prazo próximo, tarefa vencida e reunião.
- Preparar jobs/scheduler para detectar vencimentos.
- Garantir idempotência dos lembretes para evitar duplicidade.

### Visualizações

- Implementar listagem de tarefas com filtros por status, responsável, cliente, prazo e prioridade.
- Implementar visão de tarefas atrasadas e críticas.
- Implementar visão de agenda por dia, semana e mês.
- Implementar carga de trabalho por responsável.

### Permissões

- Criar policies para tarefas, prazos, modelos e eventos.
- Garantir que usuário só atribua tarefa a membro com acesso ao cliente.
- Ocultar tarefas e prazos de clientes restritos.

### Testes

- Testar criação e conclusão de tarefa.
- Testar bloqueio de conclusão com checklist obrigatório incompleto.
- Testar reatribuição para usuário sem acesso ao cliente.
- Testar criação por modelo.
- Testar prazo com revisão obrigatória.
- Testar listagens filtradas.
- Testar agenda e criação de tarefas a partir de reunião.
- Testar lembretes sem duplicidade.

## Endpoints esperados

- `GET /api/v1/tasks`
- `POST /api/v1/tasks`
- `GET /api/v1/tasks/{task}`
- `PATCH /api/v1/tasks/{task}`
- `PATCH /api/v1/tasks/{task}/status`
- `PATCH /api/v1/tasks/{task}/assign`
- `PATCH /api/v1/tasks/{task}/complete`
- `POST /api/v1/tasks/{task}/checklist-items`
- `PATCH /api/v1/task-checklist-items/{item}`
- `DELETE /api/v1/task-checklist-items/{item}`
- `GET /api/v1/task-templates`
- `POST /api/v1/task-templates`
- `POST /api/v1/task-templates/{template}/create-tasks`
- `GET /api/v1/deadlines`
- `POST /api/v1/deadlines`
- `PATCH /api/v1/deadlines/{deadline}`
- `PATCH /api/v1/deadlines/{deadline}/complete`
- `PATCH /api/v1/deadlines/{deadline}/request-review`
- `PATCH /api/v1/deadlines/{deadline}/approve-review`
- `GET /api/v1/calendar-events`
- `POST /api/v1/calendar-events`
- `PATCH /api/v1/calendar-events/{event}`
- `POST /api/v1/calendar-events/{event}/notes`

## Condições de aceite

- Tarefas possuem responsável, prazo, status e prioridade.
- Tarefas podem ser filtradas por cliente, responsável, status, prazo e prioridade.
- Atraso é calculado por data e status, não mantido manualmente como estado principal.
- Checklist pode bloquear conclusão quando obrigatório.
- Modelos geram tarefas com prazos relativos corretos.
- Prazos importantes possuem alertas e podem exigir revisão antes da conclusão.
- Agenda mostra eventos, reuniões e prazos respeitando permissões.
- Reuniões podem gerar resumo e tarefas derivadas.
- Lembretes internos são gerados sem duplicidade.
- Testes cobrem regras de status, permissões, checklist, prazos e agenda.

## Fora do escopo

- Integração com Google Calendar ou Outlook.
- Prazos jurídicos especializados.
- Obrigações contábeis mensais especializadas.
- Automações configuráveis avançadas.

