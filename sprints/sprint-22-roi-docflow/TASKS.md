# Sprint 22 — ROI Docflow (automações × horas)

## Objetivo

Mostrar no dashboard o **tempo estimado economizado** pelas automações do escritório: execuções reais × minutos padrão por tipo de ação — sem inventar dinheiro nem cronometragem.

## Referências

- [Horizonte 4 — README](../horizonte-04-prova-de-valor/README.md)
- [Sprint 20](../sprint-20-automacoes-simples/TASKS.md) / [Sprint 21](../sprint-21-dashboard-resultado-valor/TASKS.md)
- `AutomationRunner`, `automation_logs`, `ReportMetrics`, `Dashboard/Index.vue`

## Escopo funcional

### Métrica

- Conta apenas logs `succeeded` no período do dashboard.
- Minutos por tipo de ação vêm de `config/docflow.php` (`automation_roi.minutes_saved_per_action`).
- Snapshot gravado em `automation_logs.estimated_minutes_saved` na execução.
- UI deixa explícito que é **estimativa**, não tempo medido.

### Visibilidade

- Mesmo gate da página de automações: admin/manager + feature `automations` (Profissional+).
- Essencial: bloco ausente (`docflow_roi = null`).
- Sem execuções: CTA para `/automations`.

### UI (dashboard)

1. Seção após receita/comercial: horas estimadas + execuções no período.
2. Delta vs período anterior.
3. Link para automações.

## Tarefas técnicas

- [x] Config `docflow.automation_roi.minutes_saved_per_action`.
- [x] `EstimatesAutomationMinutesSaved` + persistência no `AutomationRunner`.
- [x] Migration `estimated_minutes_saved` + índice `(organization_id, status, ran_at)`.
- [x] `ReportMetrics::docflowRoiSummary` + payload do dashboard.
- [x] Bloco em `Dashboard/Index.vue`.
- [x] Testes (dashboard + runner grava minutos).
- [x] Docs ACTIVE + horizonte 4.

## Critérios de aceite

- [x] Profissional+ admin vê horas estimadas a partir de execuções reais.
- [x] Essencial não recebe `docflow_roi`.
- [x] Assistente/financeiro não vê o bloco.
- [x] Segunda org não soma logs da primeira.
- [x] Copy deixa claro que é estimativa.

## Fora de escopo

- Valor em reais do tempo (salário/hora).
- Benchmark entre tenants.
- Builder de minutos por tenant.
- Relatório dedicado / CSV de ROI.
