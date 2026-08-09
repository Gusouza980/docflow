# Sprint 17 — Gateway Asaas (billing SaaS)

## Objetivo

Substituir o gap opcional da Sprint 16: implementação real de **`AsaasBillingGateway`**, sincronização de customer/subscription/invoice com o Asaas, e processamento de webhooks com validação de assinatura/token — sem abandonar o driver `manual`.

## Referências

- [Horizonte 3 — README](../horizonte-03-comercial-operacao/README.md)
- [Sprint 16](../sprint-16-billing-self-service/TASKS.md)
- `App\Contracts\Billing\BillingGateway`
- `App\Billing\ManualBillingGateway`
- `App\Jobs\ProcessBillingWebhook`
- `App\Http\Controllers\Web\Webhooks\BillingWebhookController`
- Docs Asaas: Customers, Subscriptions, Payments, Webhooks

## Decisões de kickoff

- **Modelo Asaas:** subscription nativa (`POST /v3/subscriptions`), não payment avulso.
- **Ambiente:** sandbox `https://api-sandbox.asaas.com`.

## Pré-requisitos

- Sprint 16 MVP (manual billing + faturas + webhook genérico).

## Escopo funcional

### Driver

- `DOCFLOW_BILLING_DRIVER=asaas` seleciona `AsaasBillingGateway`.
- Driver `manual` continua default em local/test.
- Sem checkout self-service completo na UI tenant nesta sprint (platform/admin + commands geram cobrança).

### Sincronização

- Ao ativar/criar assinatura paga: garantir `provider_customer_id` e `provider_subscription_id` (ou payment avulso se subscription Asaas não couber no MVP — decidir no kickoff; default: **Payment + customer**, subscription Asaas se estável).
- Ao gerar `SubscriptionInvoice` open: criar cobrança no Asaas e gravar `provider_invoice_id`.
- Cancelamento `cancel_at_period_end` / imediato espelhado no gateway quando houver IDs.

### Webhooks

- Endpoint existente `/webhooks/billing/asaas` com validação do `asaas_webhook_secret` (header/token Asaas).
- Mapear eventos reais Asaas → ações internas (`MarkInvoicePaid`, past_due, etc.).
- Idempotência via `billing_webhook_events` (já existe).
- Falha de assinatura → 401/403 sem side effects.

### Operação

- Platform admin continua podendo mark-paid manual (fallback).
- Logs mascaram API key; payloads de webhook persistidos com cuidado (sem PII desnecessária em log de app).

## Tarefas técnicas

### HTTP client / config

- [x] Cliente HTTP tipado ou Action `App\Billing\Asaas\AsaasClient` (base URL sandbox/prod via env).
- [x] `config/docflow.php`: `billing.asaas_base_url`, confirmar keys já existentes.
- [x] Documentar sandbox em `.env.example` + README curto em comentário do gateway.

### Gateway

- [x] `App\Billing\AsaasBillingGateway` implementando `BillingGateway`.
- [x] Binding em `AppServiceProvider` quando `driver === 'asaas'`.
- [x] Persistir IDs em `subscriptions` / `subscription_invoices`.
- [x] Tratamento de erros HTTP → exception de domínio + log context (org_id, invoice_id).

### Webhook

- [x] Validar token/secret Asaas em `BillingWebhookController` (ou middleware dedicado).
- [x] Normalizar payload Asaas → formato consumido por `ProcessBillingWebhook` **ou** especializar o job com strategy por provider.
- [x] Mapear pelo menos: pagamento confirmado, pagamento vencido/estornado (definir mínimo no kickoff).
- [x] Testes com fixture de payload Asaas (sem rede).

### Integração com commands

- [x] `GenerateSubscriptionInvoice` chama `createInvoice` no gateway ativo.
- [x] Falha Asaas não deixa fatura local inconsistente (transação / status `draft` → `open` só após provider id, ou retry job — escolher e documentar).

### Testes

- [x] Unit: `AsaasBillingGateway` com HTTP fake (customer/invoice).
- [x] Feature: webhook Asaas válido → invoice paid + subscription active.
- [x] Feature: webhook com secret inválido → 401 e zero side effects.
- [x] Feature: mesmo `event_id` 2x → 1 efeito.
- [x] Feature: driver `manual` nos testes existentes não regressa.

## Endpoints

| Método | Rota | Quem |
|--------|------|------|
| POST | `/webhooks/billing/asaas` | Asaas (público + secret) |

(Rotas tenant/platform de billing da Sprint 16 permanecem.)

## Condições de aceite

- Com `DOCFLOW_BILLING_DRIVER=asaas` + credenciais sandbox, gerar fatura cria cobrança externa e grava `provider_invoice_id`.
- Webhook de pagamento confirmado reativa org/`subscription` inadimplente.
- Idempotência e rejeição de secret inválido cobertas por teste.
- Driver manual intacto (CI default).

## Fora do escopo

- UI de checkout Pix QR no portal tenant.
- Dunning multi-etapa Asaas.
- Split / subcontas.
- Trocar provedor para Stripe nesta sprint.
- NF-e.

## Ordem sugerida

1. AsaasClient + config sandbox.
2. AsaasBillingGateway (customer + invoice).
3. Hook em GenerateSubscriptionInvoice.
4. Webhook signature + mapeamento de eventos.
5. Testes HTTP fake + fixtures.
