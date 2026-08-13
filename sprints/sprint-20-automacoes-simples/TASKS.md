# Sprint 20 — Automações simples

## Objetivo

Entregar engine mínima de **automation rules** (gatilho → condições → ações) com **logs idempotentes**, gated pela feature de plano `automations`, cobrindo os gatilhos operacionais mais úteis sem builder visual complexo.

## Referências

- [Horizonte 3 — README](../horizonte-03-comercial-operacao/README.md)
- [Sprint 18](../sprint-18-crm-onboarding/TASKS.md) / [Sprint 19](../sprint-19-servicos-contratos/TASKS.md)
- `docs/briefing_app_gestao_escritorios.md` — § 7.16
- UC-119 a UC-126
- `PlanLimitChecker` / feature `automations`
- Filas + `TransactionalMailer` (Sprint 11)

## Pré-requisitos

- Sprint 18 (gatilho lead/cliente) e preferencialmente 19 (serviço/contrato).
- Feature `automations` já seedada nos planos.

## Escopo funcional

### Regras

- `automation_rules`: nome, trigger, conditions JSON, actions JSON, `is_active`, org_id.
- UI admin: listar, criar a partir de presets, pausar/ativar, ver últimas execuções.
- Sem DSL arbitrário: **presets versionados** no código + params.

### Gatilhos iniciais (MVP)

| Trigger | Quando |
|---------|--------|
| `client.created` | Cliente criado (inclui conversão CRM) |
| `document.expiring` | Documento com vencimento em N dias (scheduler) |
| `receivable.overdue` | Cobrança vencida (scheduler ou evento mark overdue) |
| `contract.expiring` | Contrato a vencer em N dias (se Sprint 19 ok) |
| `lead.stage_changed` | Lead entrou em stage X (opcional) |

### Ações iniciais (MVP)

| Action | Efeito |
|--------|--------|
| `create_tasks_from_template` | Usa `TaskTemplate` |
| `create_document_request` | Solicita docs (categoria/itens) |
| `notify_organization_members` | Notificação interna / e-mail fila |
| `send_message_template` | Portal ou e-mail via template existente |
| `create_receivable_recurrence` | Só se contrato/serviço ativo (cuidado) |

### Idempotência e logs

- `automation_logs`: rule_id, trigger, subject_type/id, dedupe_key unique, status, result JSON, ran_at.
- Mesma `dedupe_key` não reexecuta ação com side effect.
- Falhas registradas; retry manual opcional (fora do MVP ok).

## Tarefas técnicas

### Modelagem

- [ ] Migration `automation_rules`.
- [ ] Migration `automation_logs` (unique `organization_id + dedupe_key` ou `rule_id + dedupe_key`).
- [ ] Models + factories.
- [ ] Enums/constants `AutomationTrigger`, `AutomationAction` em `App\Enums` ou config.

### Engine

- [ ] `App\Automations\AutomationRunner` — resolve regras ativas do trigger, avalia conditions, executa actions.
- [ ] `App\Automations\Actions\*` uma classe por action.
- [ ] Dispatch async via job `RunAutomationRule` quando o trigger for síncrono em request.
- [ ] Commands scheduler para triggers temporais (`automations:dispatch-expiring-documents`, etc.) **ou** um único `automations:dispatch-due`.

### Enforcement de plano

- [ ] `PlanLimitChecker::assertFeature($org, 'automations')` ao criar/ativar regra e antes de runner (fail closed).
- [ ] UI esconde ou explica upgrade se feature ausente.

### Integração nos fluxos

- [ ] Hook em `CreateOrganization`/`ClientController@store` / `ConvertLeadToClient` → `client.created`.
- [ ] Hook stage lead → `lead.stage_changed`.
- [ ] Scheduler para documentos/contratos/receivables.

### Controllers / UI

- [ ] `AutomationRuleController` — index, store (presets), pause, resume, show logs.
- [ ] Páginas `Automations/Index.vue`, `Show.vue`.
- [ ] Link sidebar (admin/manager).

### Testes

- [ ] Unit: runner aplica action e grava log.
- [ ] Feature: segunda execução mesma dedupe_key não duplica tasks.
- [ ] Feature: plano sem `automations` → 403/422 ao ativar.
- [ ] Feature: `client.created` dispara preset de onboarding (tasks).
- [ ] Feature: isolamento tenant.

## Endpoints (web MVP)

| Método | Rota | Quem |
|--------|------|------|
| GET/POST | `/automations` | admin/manager |
| GET | `/automations/{rule}` | admin/manager |
| POST | `/automations/{rule}/pause` | admin |
| POST | `/automations/{rule}/resume` | admin |
| GET | `/automations/{rule}/logs` | admin/manager |

## Condições de aceite

- Pelo menos 3 triggers e 3 actions funcionando em produção local com fila.
- Logs auditáveis e idempotentes.
- Feature de plano respeitada.
- Nenhuma automação envia WhatsApp externo nesta sprint.

## Fora do escopo

- Builder visual drag-and-drop / grafo de condições arbitrárias.
- Automações multi-org / marketplace de regras.
- Compensating transactions complexas.
- IA para sugerir regras.

## Ordem sugerida

1. Tables + enums + AutomationRunner no-op.
2. 2 actions + 1 trigger síncrono (`client.created`).
3. Logs + idempotência.
4. UI presets.
5. Triggers scheduler.
6. Gate de plano + testes.
