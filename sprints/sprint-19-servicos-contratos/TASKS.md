# Sprint 19 — Serviços contratados e contratos

## Objetivo

Permitir que o escritório mantenha um **catálogo de tipos de serviço**, vincule **serviços ao cliente** e registre **contratos** com vigência, valores, recorrência e escopo — base para renovação, financeiro e automações (Sprint 20).

## Referências

- [Horizonte 3 — README](../horizonte-03-comercial-operacao/README.md)
- [Sprint 18](../sprint-18-crm-onboarding/TASKS.md)
- `docs/briefing_app_gestao_escritorios.md` — § 7.5
- UC-032 a UC-037
- Financeiro existente: `Receivable`, `ReceivableRecurrence`

## Pré-requisitos

- Sprint 18 (cliente com origem CRM opcional).
- Módulo financeiro básico (para opcionalmente sugerir recorrência — sem gateway do escritório).

## Escopo funcional

### Catálogo

- `service_types` por organização: nome, descrição, ativo, defaults (valor sugerido, recorrência sugerida).
- CRUD admin/manager.

### Serviços do cliente

- `client_services`: client + service_type, status (`active`, `paused`, `ended`), datas, responsável interno, notas.
- Visível na ficha do cliente.

### Contratos

- `contracts`: client, opcionalmente client_service_ids, número/código interno, status (`draft`, `active`, `expired`, `canceled`).
- Valores: `amount_cents`, `billing_interval` (month/year/once), vigência `starts_at` / `ends_at`, `auto_renew`.
- Escopo: `scope_included` / `scope_excluded` (text/json), anexos via documentos existentes (morph ou `document_id`s).
- Alertas: listagem “vencendo em 30 dias” + card no dashboard (reutilizar padrão Sprint 09).

### Renovação

- Action `RenewContract`: estende vigência, opcionalmente gera novo período; audit log tenant.
- Encerrar serviço/contrato preservando histórico.

## Tarefas técnicas

### Modelagem

- [x] Migration `service_types`.
- [x] Migration `client_services`.
- [x] Migration `contracts` (+ pivot `contract_client_service` se N:N).
- [x] Models + factories; soft deletes onde fizer sentido (`ended` preferível a delete físico).
- [x] Permissions / policies (`contracts.manage`).

### Actions

- [x] `UpsertClientService` (store de tipo via controller).
- [x] `CreateContract`, `RenewContract`, `CancelContract`.
- [x] (Opcional MVP+) Ao ativar contrato recorrente, sugerir/criar `ReceivableRecurrence` — movido para [Sprint 23](../sprint-23-contrato-gera-cobranca/TASKS.md).

### Controllers / UI

- [x] `ServiceTypeController`.
- [x] `ClientServiceController` (nested sob client ou resource próprio).
- [x] `ContractController` — index (filtros status/vencimento), show, store, renew, cancel.
- [x] Seções na ficha do cliente: Serviços, Contratos.
- [x] Dashboard: contagem contratos a vencer.

### Testes

- [x] Feature: CRUD service type isolado por org.
- [x] Feature: vincular serviço ao cliente.
- [x] Feature: criar contrato active com vigência.
- [x] Feature: renew estende `ends_at`.
- [x] Feature: listagem “vencendo em 30 dias”.
- [x] Feature: assistente read-only se policy assim definir.

## Endpoints (web MVP)

| Método | Rota | Quem |
|--------|------|------|
| GET/POST | `/service-types` | admin/manager |
| POST | `/clients/{client}/services` | equipe |
| GET/POST | `/contracts` | equipe |
| GET | `/contracts/{contract}` | equipe |
| POST | `/contracts/{contract}/renew` | manager/admin |
| POST | `/contracts/{contract}/cancel` | manager/admin |

## Condições de aceite

- Catálogo + serviços do cliente + contrato visíveis na ficha.
- Renovação e cancelamento auditáveis.
- Consulta de vencimentos utilizável na operação semanal.
- Sem misturar com billing SaaS (`subscription_invoices`).

## Fora do escopo

- Assinatura digital (DocuSign etc.).
- Geração de PDF de contrato a partir de template rico.
- Reajuste automático por índice (IPCA) — campo manual ok.
- Honorários advocatícios especializados (vertical).

## Ordem sugerida

1. service_types + UI.
2. client_services na ficha.
3. contracts + renew/cancel.
4. Alertas de vencimento.
5. Hook opcional recorrência financeira.
6. Testes.
