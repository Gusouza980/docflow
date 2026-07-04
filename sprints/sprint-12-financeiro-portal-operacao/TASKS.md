# Sprint 12 — Financeiro ↔ portal, lembretes automáticos e observabilidade

## Objetivo

Fechar o loop **cobrança ↔ cliente**: alertas de inadimplência no portal, visibilidade financeira para o cliente, jobs de lembrete automatizados e operação confiável (auditoria + monitoramento de jobs).

## Referências

- Sprint 05 estendida (recorrência, renegociação, `receivable_reminders`)
- `app/Actions/Notifications/NotifyPortalClient.php`
- `app/Support/BuildsClientPortalDashboard.php` — finance no portal
- `app/Actions/Organizations/RecordAuditLog.php`

## Pré-requisitos

- **Sprint 11** (e-mail + fila) para lembretes automáticos com notificação real.
- Sprint 09 (alertas financeiros no dashboard) recomendada.

## Escopo funcional

### Financeiro ↔ portal

- Cliente vê cobranças vencidas/em aberto no portal (`/client-portal/finance`) com destaque visual.
- Job ou action: cobrança vencida há N dias → `NotifyPortalClient` + `PortalClientAlert` (tipo finance).
- Link “Ver cobrança” no portal (sem pagamento online — apenas detalhe da cobrança).
- Equipe registra lembrete manual (já existe) — enriquecer com opção “notificar cliente no portal”.
- Opcional: marcar cliente como `delinquent` após X dias vencido (job idempotente).

### Link de pagamento (simulado)

- Campo `payment_reference` ou página estática “Instruções de pagamento” por organização (texto configurável).
- Sem gateway — preparar URL placeholder `payment_url` nullable em receivable para integração futura.

### Observabilidade

- Página ou seção **Auditoria** (admin): últimos N eventos `audit_logs` da organização (filtro por ação).
- Listagem de **jobs recentes** / failed jobs (somente admin) — ou comando artisan documentado.
- Métricas no dashboard interno: última execução de `finance:generate-recurring-receivables` e `reports:run-schedules` (tabela `scheduler_runs` simples ou cache).

## Tarefas técnicas

### Cobrança vencida → portal

- [ ] Criar `App\Actions\Finance\NotifyOverdueReceivableToClient`:
  - só receivables open/partial + overdue;
  - evita spam: no máximo 1 alerta portal a cada N dias por receivable (tabela ou coluna `last_portal_reminder_at`).
- [ ] Command `finance:notify-overdue-receivables` + scheduler (ex.: semanal ou diário).
- [ ] Integrar botão “Notificar cliente” no lembrete manual (`FinanceController::storeReceivableReminder`).

### Portal UI

- [ ] Melhorar `ClientPortal/Finance.vue`: badge vencido, total em aberto, ordenação por vencimento.
- [ ] Detalhe da cobrança (modal ou linha expandida): descrição, valor, vencimento, instruções de pagamento.

### Organização — instruções de pagamento

- [ ] Migration: `organizations.payment_instructions` (text nullable) ou settings JSON.
- [ ] Edição em `/organizations` (admin) — texto exibido no portal financeiro.

### Inadimplência automática (opcional)

- [ ] Command `finance:mark-delinquent-clients`: clientes com saldo vencido > 0 há 30 dias → `Client::STATUS_DELINQUENT`.
- [ ] Notificar responsável interno (`NotifyTeamMembers`).

### Observabilidade

- [ ] Migration `scheduler_run_logs` (command, ran_at, duration_ms, result, meta JSON) — registrar no terminate de commands críticos.
- [ ] Middleware ou trait `LogsSchedulerRun` para commands: finance, reports.
- [ ] Página web `GET /settings/audit` ou seção em `/organizations`:
  - lista paginada audit logs;
  - filtro por ação e usuário;
  - somente admin/manager.
- [ ] Exibir contagem `failed_jobs` recentes para admin (link para retry doc).

### Testes

- [ ] Feature: job overdue cria `portal_client_alerts` + notification (fake).
- [ ] Feature: segundo alerta no mesmo período não duplica (idempotência).
- [ ] Feature: lembrete manual com “notificar cliente” dispara NotifyPortalClient.
- [ ] Feature: audit page restrita a admin; assistente 403.
- [ ] Feature: scheduler log escrito após command.

## Endpoints

- `POST /finance/receivables/{receivable}/reminders` — body estendido: `notify_client` boolean.
- `PATCH /organizations/{organization}` — `payment_instructions`.
- `GET /audit` ou `/organizations/audit` (Inertia).

## Condições de aceite

- Cliente inadimplente recebe alerta no portal (in-app + e-mail se Sprint 11 ok).
- Equipe controla lembrete manual com opção de notificar cliente.
- Instruções de pagamento visíveis no portal sem gateway.
- Admin consulta auditoria recente da organização.
- Commands financeiros e de relatório registram execução.
- Testes cobrem idempotência de alertas e permissões.

## Fora do escopo

- Gateway Pix/boleto/cartão.
- Geração de boleto PDF.
- Dashboard de infraestrutura completo (Grafana/Datadog).

## Ordem sugerida de implementação

1. `payment_instructions` + UI portal financeiro.
2. `NotifyOverdueReceivableToClient` + idempotência + testes.
3. Command + scheduler + integração lembrete manual.
4. `scheduler_run_logs` + registro nos commands existentes.
5. Página auditoria web.
