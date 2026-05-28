# Sprint 07 — Relatórios e Indicadores

## Objetivo

Consolidar indicadores operacionais, documentais, financeiros, comerciais básicos e relatórios para gestão e clientes, com filtros, permissões, exportação controlada e bases para agendamento futuro.

## Referências

- `docs/briefing_app_gestao_escritorios.md`
- `docs/documento_tecnico_app_gestao_escritorios.md`
- `docs/casos_de_uso_app_gestao_escritorios.md`
- Casos de uso: UC-100 a UC-108, UC-012, UC-014, UC-095, UC-098.

## Escopo funcional

- Gerar relatório de visão geral do escritório.
- Gerar relatório de produtividade.
- Gerar relatório de documentos pendentes e vencidos.
- Gerar relatório financeiro.
- Gerar relatório comercial básico, se CRM já estiver disponível.
- Gerar relatório mensal para cliente.
- Exportar relatório.
- Salvar filtros de relatório.
- Agendar envio de relatório em estrutura inicial ou simulação.
- Melhorar painel com alertas críticos e indicadores.

## Tarefas técnicas

### Modelagem e estrutura

- Criar tabela `saved_report_filters`.
- Criar tabela `generated_reports`, se relatórios forem materializados.
- Criar tabela `report_deliveries`, se envio/liberação for controlado.
- Criar estruturas de query dedicadas para relatórios complexos.
- Definir DTOs ou Resources específicos para retorno de indicadores.
- Garantir escopo por `organization_id` em todas as consultas.

### Painel e alertas

- Evoluir endpoint de dashboard.
- Exibir indicadores de tarefas abertas, atrasadas e concluídas.
- Exibir documentos pendentes, vencidos e próximos do vencimento.
- Exibir cobranças abertas, vencidas e recebidas.
- Exibir clientes ativos, em risco, inadimplentes e com pendências críticas.
- Exibir alertas com link para recurso relacionado.

### Relatórios operacionais

- Implementar visão geral do escritório por período.
- Implementar produtividade por colaborador.
- Implementar tarefas concluídas, atrasadas e tempo médio quando dados existirem.
- Implementar clientes com mais demandas e pendências.
- Garantir que relatórios respeitem acesso a clientes restritos.

### Relatórios documentais

- Implementar relatório de documentos pendentes.
- Implementar relatório de documentos vencidos.
- Implementar relatório de documentos próximos do vencimento.
- Permitir filtros por cliente, categoria, responsável, status e período.

### Relatórios financeiros

- Implementar faturamento por período.
- Implementar recebimentos por período.
- Implementar inadimplência por cliente.
- Implementar despesas por categoria.
- Implementar previsão de recebimento.
- Implementar rentabilidade básica por cliente e serviço quando disponível.
- Bloquear relatório financeiro para usuários sem permissão.

### Relatórios para cliente

- Implementar geração de relatório mensal do cliente.
- Consolidar atividades, tarefas concluídas, documentos, solicitações, chamados e financeiro visível.
- Exigir revisão interna antes de liberar ao cliente.
- Permitir liberar relatório no portal.
- Registrar visualização ou download pelo cliente.

### Exportação e filtros

- Implementar exportação controlada em CSV ou PDF conforme viabilidade inicial.
- Registrar auditoria de exportação.
- Implementar filtros salvos por usuário.
- Permitir filtros organizacionais quando gestor/admin salvar visão compartilhada.

### Agendamento

- Implementar estrutura inicial para agendamento de relatórios.
- Se envio automático ainda não for implementado, deixar agendamento como cadastro planejado e sem execução real.
- Garantir que execução futura seja por job idempotente.

### Performance

- Revisar índices usados pelos relatórios.
- Evitar N+1 nas consultas.
- Usar agregações no banco quando fizer sentido.
- Para relatórios pesados, preparar geração assíncrona.

### Testes

- Testar relatórios respeitando permissões.
- Testar que usuário sem financeiro não acessa relatório financeiro.
- Testar indicadores de dashboard.
- Testar relatório documental.
- Testar relatório financeiro básico.
- Testar relatório mensal do cliente e liberação no portal.
- Testar exportação auditada.
- Testar filtros salvos.

## Endpoints esperados

- `GET /api/v1/dashboard`
- `GET /api/v1/reports/overview`
- `GET /api/v1/reports/productivity`
- `GET /api/v1/reports/documents`
- `GET /api/v1/reports/finance`
- `GET /api/v1/reports/clients/{client}/monthly`
- `POST /api/v1/reports/clients/{client}/monthly`
- `PATCH /api/v1/reports/{report}/release-to-client`
- `POST /api/v1/reports/export`
- `GET /api/v1/report-filters`
- `POST /api/v1/report-filters`
- `PATCH /api/v1/report-filters/{filter}`
- `DELETE /api/v1/report-filters/{filter}`
- `GET /api/v1/report-schedules`
- `POST /api/v1/report-schedules`

## Condições de aceite

- Dashboard apresenta indicadores úteis e alertas críticos com base nos módulos já implementados.
- Relatórios respeitam organização ativa, permissões financeiras e restrições de cliente.
- Relatório documental identifica pendências, vencidos e próximos vencimentos.
- Relatório financeiro distingue aberto, recebido, vencido e despesa.
- Relatório mensal do cliente pode ser revisado e liberado ao portal.
- Exportações exigem permissão e geram auditoria.
- Filtros salvos podem ser reutilizados.
- Consultas não apresentam N+1 óbvio nos cenários testados.
- Testes cobrem permissões, filtros e cálculos essenciais.

## Fora do escopo

- BI avançado.
- Relatórios customizados por construtor visual.
- Data warehouse.
- IA para perguntas gerenciais.
- Envio automático real por e-mail/WhatsApp se provedores ainda não existirem.

