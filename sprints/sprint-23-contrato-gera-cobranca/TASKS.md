# Sprint 23 — Contrato gera cobrança recorrente

## Objetivo

Ao ativar ou renovar um contrato **recorrente** (mensal/anual), o escritório pode criar — com flag explícita — uma `ReceivableRecurrence` ligada ao contrato. Cancelar o contrato pausa a cobrança. O MRR do dashboard passa a ter lastro no financeiro.

## Referências

- [Horizonte 5 — README](../horizonte-05-rotina-diaria/README.md)
- [Sprint 19](../sprint-19-servicos-contratos/TASKS.md) — leftover opcional
- `CreateContract`, `RenewContract`, `CancelContract`
- `ReceivableRecurrence`, `GenerateReceivableRecurrences`

## Escopo funcional

- Flag `create_receivable_recurrence` no criar (status ativo) e no renovar.
- Só mensal/anual com `amount_cents > 0`. Único (`once`) ignora a flag.
- Uma recorrência por contrato (`contract_id`).
- Mensal → `frequency = monthly`, valor do contrato.
- Anual → `frequency = yearly`, valor do contrato (uma cobrança por ano).
- Renovar: se já existe recorrência, atualiza `end_date`/`amount_cents` e reativa; se a flag estiver marcada e não existir, cria.
- Cancelar contrato: `is_active = false` nas recorrências do contrato.
- UI: checkbox só para admin/manager; copy deixa claro que gera mensalidade no financeiro.

## Tarefas técnicas

- [x] Migration `receivable_recurrences.contract_id` (nullable, unique) + índice.
- [x] `ReceivableRecurrence::FREQUENCY_YEARLY` e avanço anual em `GenerateReceivableRecurrences`.
- [x] Action `SyncContractReceivableRecurrence`.
- [x] Hook em create / renew / cancel (mesma transação).
- [x] Validação da flag nos Form Requests.
- [x] Checkbox em `Contracts/Index.vue` e `Show.vue` (renovar).
- [x] Expor recorrência vinculada no show do contrato.
- [x] Testes de create, renew, cancel, once, isolamento e idempotência.

## Critérios de aceite

- [x] Contrato mensal ativo + flag cria uma recorrência com o mesmo valor e `client_id`.
- [x] Sem a flag, nenhum registro financeiro é criado.
- [x] Contrato `once` com flag não cria recorrência.
- [x] Renovar estende `end_date` da recorrência existente; flag cria se faltava.
- [x] Cancelar pausa a recorrência (`is_active = false`).
- [x] Segunda criação no mesmo contrato não duplica recorrência.
- [x] Recorrência não mistura com `subscription_invoices` (billing SaaS).

## Fora de escopo

- Gateway Pix/boleto do cliente (Sprint 24).
- Gerar a primeira fatura na hora (o scheduler `finance:generate-recurring-receivables` já existe).
- Reajuste por índice (IPCA).
- Checkbox para assistente/profissional.
