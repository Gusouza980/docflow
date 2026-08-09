# Sprint ativa

**Status:** Sprint 17 implementada — próxima **Sprint 18**  
**Última sync:** 2026-08-09  
**Branch:** `feat/sprint-17-asaas-billing-gateway`

## Concluído

| Sprint | Foco | Estado |
|--------|------|--------|
| 13–16 | Horizonte 2 (platform + planos + assinaturas + billing MVP) | [x] |
| [17](sprint-17-asaas-billing-gateway/TASKS.md) | Gateway Asaas (subscription nativa + webhooks) | [x] |

## Em foco

| Sprint | Foco | Estado |
|--------|------|--------|
| [18](sprint-18-crm-onboarding/TASKS.md) | CRM + onboarding | ⬜ próxima |
| [19](sprint-19-servicos-contratos/TASKS.md) | Serviços e contratos | ⬜ |
| [20](sprint-20-automacoes-simples/TASKS.md) | Automações simples | ⬜ |

Plano: [Horizonte 3 — Comercial e operação](horizonte-03-comercial-operacao/README.md).

## Notas operacionais (Sprint 17)

- Driver: `DOCFLOW_BILLING_DRIVER=asaas`
- Sandbox: `ASAAS_BASE_URL=https://api-sandbox.asaas.com`
- Webhook auth: header `asaas-access-token` = `ASAAS_WEBHOOK_SECRET`
- Chaves Asaas começam com `$aact_…` — no `.env` escapar `$` como `\$`
