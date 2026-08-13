# Sprint 16 — Billing, faturas e self-service do tenant

## Objetivo

Completar o loop comercial: **faturas** da assinatura SaaS, registro manual ou via **gateway** (MVP: manual; integração Asaas ou Stripe opcional), webhooks idempotentes, e-mails transacionais de billing, e self-service básico para admin da org (histórico, upgrade de plano).

## Referências

- [Horizonte 2 — README](../horizonte-02-platform-billing/README.md)
- [Sprint 15](../sprint-15-assinaturas-ciclo-vida/TASKS.md)
- `app/Contracts/Mail/TransactionalMailer.php` (Sprint 11)
- `docs/documento_tecnico_app_gestao_escritorios.md` — seção 14 (pagamentos)

## Pré-requisitos

- Sprint 15 (subscriptions, enforcement, platform gestão de assinatura).
- Sprint 11 (e-mail transacional + filas).

## Escopo funcional

### Faturas (SaaS)

- Fatura por período de assinatura: `subscription_invoices`.
- Status: `draft`, `open`, `paid`, `void`, `uncollectible`.
- Geração automática ao fim do trial e a cada renovação mensal (command).
- Platform admin marca fatura como paga (MVP manual).
- Pagamento de fatura `open` → subscription `active`; inadimplência → `past_due`.

### Gateway (abstração)

- Interface `BillingGateway` desacoplada.
- Implementações:
  - **`ManualBillingGateway`** (MVP obrigatório) — sem API externa.
  - **`AsaasBillingGateway`** ou **`StripeBillingGateway`** (opcional nesta sprint — flag env).
- Webhooks → job `ProcessBillingWebhook` com idempotência (`billing_webhook_events`).

### Self-service tenant (admin da org)

- Página `/organizations/billing`:
  - Plano atual e preço.
  - Próxima fatura / última paga.
  - Histórico de faturas.
  - Botão “Alterar plano” (upgrade/downgrade entre planos públicos).
  - Botão “Cancelar assinatura” (`cancel_at_period_end`).
- Upgrade imediato; downgrade aplicado no fim do período (ou imediato — definir no kickoff).

### E-mails transacionais

- Trial expirando (D-3, D-1).
- Fatura emitida (`open`).
- Pagamento confirmado.
- Assinatura suspensa por inadimplência.
- Usar `TransactionalMailer` + fila.

### Platform admin — billing

- Listagem de faturas cross-tenant (filtro: status, vencidas).
- Marcar paga / void / reemitir.
- Dashboard: MRR estimado, orgs inadimplentes, trials expirando em 7 dias.

## Tarefas técnicas

### Modelagem

- [x] Migration `subscription_invoices`:
  ```text
  subscription_id, organization_id
  amount_cents, currency (BRL default)
  status, due_at, paid_at
  provider_invoice_id nullable
  payment_method nullable, metadata json
  ```
- [x] Migration `billing_webhook_events` (idempotência):
  ```text
  provider, event_id (unique), payload json, processed_at
  ```
- [x] Models + factories.

### Gateway

- [x] `App\Contracts\Billing\BillingGateway`:
  - `createCustomer(Organization)`, `createSubscription(Subscription)`
  - `cancelSubscription(Subscription, bool $atPeriodEnd)`
  - `createInvoice(SubscriptionInvoice)` (opcional MVP manual)
- [x] `App\Billing\ManualBillingGateway` — implementação no-op / local.
- [x] Binding em `AppServiceProvider`: `config('docflow.billing.driver')`.
- [ ] (Opcional) `AsaasBillingGateway` — customer + subscription + webhook signature.
- [x] `App\Jobs\ProcessBillingWebhook` — dispatch por provider.

### Actions / commands

- [x] `App\Actions\Billing\GenerateSubscriptionInvoice` — cria fatura `open` para período.
- [x] `App\Actions\Billing\MarkInvoicePaid` — paid + ativa subscription + audit.
- [x] `App\Actions\Billing\MarkInvoicePastDue` — subscription past_due.
- [x] `App\Actions\Billing\RequestPlanChange` — tenant self-service upgrade/downgrade.
- [x] Command `billing:generate-invoices` — faturas de renovação (mensal) + pós-trial.
- [x] Command `billing:mark-overdue-invoices` — open + due_at passado → past_due.
- [x] Scheduler: daily após `subscriptions:*`.

### Notificações

- [x] `SubscriptionTrialEndingNotification` — admin(s) da org.
- [x] `SubscriptionInvoiceIssuedNotification`.
- [x] `SubscriptionPaymentConfirmedNotification`.
- [x] `SubscriptionSuspendedNotification`.
- [x] Command `billing:notify-trial-ending` (D-3, D-1).

### Controllers e rotas

- [x] `OrganizationBillingController` (tenant):
  - `GET /organizations/billing`
  - `POST /organizations/billing/change-plan`
  - `POST /organizations/billing/cancel`
- [x] `Platform\InvoiceController` — index cross-tenant, markPaid, void.
- [x] `Webhooks\BillingWebhookController` — POST público com secret (`/webhooks/billing/{provider}`; Asaas/Stripe dedicados ficam no stretch).
- [x] Atualizar `Platform\DashboardController` — widgets MRR, inadimplentes, trials.

### Frontend

- [x] `Organizations/Billing.vue` — plano, faturas, ações self-service.
- [x] `Platform/Invoices/Index.vue` — listagem global.
- [x] Modal confirmar upgrade/downgrade com diff de limites.
- [x] Link “Billing” em `/organizations` ou sidebar (admin only).

### Config / env

- [x] `config/docflow.php`:
  ```php
  'billing' => [
      'driver' => env('DOCFLOW_BILLING_DRIVER', 'manual'),
      'asaas_api_key' => env('ASAAS_API_KEY'),
      'asaas_webhook_secret' => env('ASAAS_WEBHOOK_SECRET'),
  ],
  ```
- [x] Documentar em `.env.example`.

### Testes

- [x] Unit: `ManualBillingGateway` fluxos básicos.
- [x] Feature: trial expirado gera fatura open (command).
- [x] Feature: mark paid reativa subscription past_due.
- [x] Feature: fatura overdue marca subscription past_due.
- [x] Feature: tenant admin change plan upgrade altera plan_id.
- [x] Feature: cancel at period end não bloqueia até fim do período.
- [x] Feature: webhook idempotente (mesmo event_id 2x → 1 efeito).
- [x] Feature: e-mail trial ending enfileirado (fake mail/queue).
- [x] Feature: platform admin mark invoice paid auditado.

## Endpoints

| Método | Rota | Quem |
|--------|------|------|
| GET | `/organizations/billing` | Admin org |
| POST | `/organizations/billing/change-plan` | Admin org |
| POST | `/organizations/billing/cancel` | Admin org |
| GET | `/platform/invoices` | Platform admin |
| POST | `/platform/invoices/{invoice}/mark-paid` | Platform admin |
| POST | `/webhooks/billing/{provider}` | Gateway (público + secret) |

## Condições de aceite

- Fatura gerada automaticamente ao converter trial ou renovar mês.
- Pagamento manual pelo platform admin reativa org inadimplente.
- Tenant admin vê histórico e solicita upgrade/downgrade.
- E-mails de trial e inadimplência enfileirados.
- Webhook (se gateway habilitado) processado uma única vez.
- MRR e inadimplentes visíveis no dashboard platform (cálculo simples: soma `price_cents` de subscriptions active).

## Fora do escopo

- Nota fiscal eletrônica (NF-e/NFS-e).
- Cupom de desconto / promo codes.
- Múltiplos métodos de pagamento na UI (Pix QR inline).
- Dunning automático multi-etapa (cobrança recorrente agressiva).
- Billing do financeiro interno do escritório (continua separado).

## Ordem sugerida de implementação

1. Migrations faturas + webhook events.
2. ManualBillingGateway + GenerateInvoice + MarkPaid.
3. Commands generate/overdue + scheduler.
4. Tenant billing UI + change plan.
5. Platform invoices + dashboard MRR.
6. Notificações + notify-trial-ending.
7. (Opcional) Gateway Asaas + webhook.
8. Testes.

## Nota sobre gateway

**Recomendação MVP:** entregar Sprint 16 completa com `ManualBillingGateway` + testes; Asaas como stretch goal na mesma sprint ou Sprint 17 fina. Isso desbloqueia operação comercial manual enquanto integração externa amadurece.
