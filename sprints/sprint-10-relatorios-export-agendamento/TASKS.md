# Sprint 10 — Relatórios: exportação web e agendamento executável

## Objetivo

Fechar a Sprint 07 no que falta para **uso gerencial real**: exportar relatórios pela web, executar agendamentos via job idempotente e registrar entregas/liberações quando aplicável.

## Referências

- `sprints/sprint-07-relatorios-indicadores/TASKS.md`
- `app/Http/Controllers/Web/ReportController.php`
- `app/Http/Controllers/Api/V1/ReportController.php` (export CSV existente)
- `app/Models/ReportSchedule.php`, `GeneratedReport`

## Pré-requisitos

- Sprint 09 recomendada (mesmas métricas), mas não bloqueante.
- Tabela `report_schedules` e UI de cadastro em `/reports`.

## Escopo funcional

- Botão **Exportar CSV** em `/reports` para tipos: overview, productivity, documents, finance (com permissão).
- Job diário/semanal que processa `ReportSchedule` ativos com `next_run_at <= hoje`.
- Geração idempotente: mesmo schedule + período não duplica `GeneratedReport`.
- Atualizar `last_run_at` e calcular próximo `next_run_at` conforme `frequency`.
- Relatório mensal por cliente: gerar rascunho, opcionalmente liberar ao portal (fluxo já parcialmente existente).
- Registrar auditoria: exportação, execução de schedule, falha de job.
- UI: status do último run, próxima execução, botão “Executar agora” (admin).

## Tarefas técnicas

### Modelagem (se necessário)

- [ ] Avaliar tabela `report_deliveries` (schedule_id, generated_report_id, channel, status, sent_at) — criar se entrega simulada for registrada.
- [ ] Adicionar em `report_schedules`: `last_error`, `consecutive_failures` (opcional) para observabilidade.

### Action — execução de agendamento

- [ ] Criar `App\Actions\Reports\RunReportSchedule`:
  - recebe `ReportSchedule`;
  - resolve período conforme `frequency` (mensual = mês anterior ou mês corrente — documentar decisão);
  - chama `ReportMetrics` + persiste `GeneratedReport`;
  - para `report_type = client_monthly`, exige `client_id`;
  - avança `next_run_at` idempotentemente;
  - retorna `GeneratedReport` ou null se já existir para o período.
- [ ] Definir chave de idempotência: `(schedule_id, report_type, period_start, period_end)` ou campo em `generated_reports`.

### Command + scheduler

- [ ] Criar `reports:run-schedules` (processa todos due).
- [ ] Registrar no `bootstrap/app.php` scheduler (ex.: diário 07:00, `withoutOverlapping`).
- [ ] Tratar exceções: log + atualizar schedule com erro, não travar demais schedules.

### Exportação web

- [ ] Rota `POST /reports/export` (web) espelhando API:
  - validação de tipo + filtros;
  - `StreamedResponse` CSV;
  - `RecordAuditLog` (`web.report.exported`).
- [ ] Reutilizar lógica de `Api\V1\ReportController::export` via action compartilhada `ExportReportCsv`.
- [ ] Botões na UI `/reports` por aba/seção de relatório.
- [ ] Bloquear financeiro para quem não tem permissão.

### UI — Reports/Index.vue

- [ ] Botão exportar CSV com filtros atuais.
- [ ] Coluna/ação “Executar agora” por schedule.
- [ ] Exibir `last_run_at`, `next_run_at`, badge de erro se `last_error`.
- [ ] Feedback flash após export (download iniciado).

### Testes

- [ ] Feature: export web gera CSV e audit log.
- [ ] Feature: schedule mensal gera `GeneratedReport` uma vez; segunda execução no mesmo período não duplica.
- [ ] Feature: `reports:run-schedules` atualiza `next_run_at`.
- [ ] Feature: usuário sem financeiro não exporta tipo `finance`.
- [ ] Feature: “Executar agora” via web para schedule da organização.

## Endpoints

- `POST /reports/export`
- `POST /reports/schedules/{schedule}/run` (execução manual)
- Comando: `php artisan reports:run-schedules`

## Condições de aceite

- Export CSV funciona pela web com mesmas regras da API.
- Schedules ativos geram relatórios automaticamente via scheduler.
- Execução é idempotente por período.
- Falhas ficam registradas sem corromper `next_run_at` permanentemente.
- Relatório mensal gerado por schedule pode ser liberado ao portal (fluxo existente).
- Testes cobrem happy path, permissões e idempotência.

## Fora do escopo

- Envio real por e-mail/WhatsApp do PDF/CSV (preparar hook para Sprint 11).
- Export PDF.
- Construtor visual de relatórios.

## Ordem sugerida de implementação

1. Extrair `ExportReportCsv` action (API + web).
2. UI export + testes.
3. `RunReportSchedule` action + idempotência.
4. Command + scheduler + testes.
5. UI “Executar agora” + status de schedule.
