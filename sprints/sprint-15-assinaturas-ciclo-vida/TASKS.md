# Sprint 15 — Assinaturas, trial e enforcement de acesso

## Objetivo

Vincular cada organização a uma **assinatura** com ciclo de vida (trial → active → past_due → canceled), bloquear acesso quando org suspensa ou assinatura inválida (web, API, portal), e permitir gestão de assinatura pelo platform admin.

## Referências

- [Horizonte 2 — README](../horizonte-02-platform-billing/README.md)
- [Sprint 14](../sprint-14-planos-limites/TASKS.md)
- `app/Http/Middleware/EnsureOrganizationIsActive.php`
- `app/Support/WebOrganizationContext.php`
- `app/Actions/Organizations/CreateOrganization.php`

## Pré-requisitos

- Sprint 14 (planos, `PlanLimitChecker`, `ResolvesOrganizationPlan`).

## Escopo funcional

### Assinatura por organização

- Relação 1:1 `Organization` ↔ `Subscription` (ativa).
- Status: `trialing`, `active`, `past_due`, `canceled`, `paused`.
- Trial automático na criação de org (14 dias, plano Essencial ou escolhido no signup).
- Campos de período: `trial_ends_at`, `current_period_start`, `current_period_end`.
- `billing_provider`: `manual` no MVP (gateway na Sprint 16).

### Ciclo de vida automatizado

- Command `subscriptions:expire-trials` — trial expirado sem pagamento → `canceled` + opcional suspend org.
- Command `subscriptions:mark-past-due` — fatura vencida (Sprint 16 prepara; aqui simular ou campo manual).
- Grace period configurável (`config/docflow.php`: `subscription_grace_days`, default 7).
- Após grace: `canceled` + `organization.status = suspended`.

### Enforcement de acesso unificado

- Middleware `EnsureOrganizationAccessible`:
  1. Org não suspensa manualmente.
  2. Assinatura permite acesso: `trialing` (dentro do prazo), `active`, `past_due` (dentro do grace).
  3. Membership ativa (já existente).
- Aplicar em:
  - **Web:** grupo `auth` tenant (exceto rotas de billing/plan e logout).
  - **API:** substituir/estender `EnsureOrganizationIsActive`.
  - **Portal:** middleware portal ou check no controller base.
- Página dedicada `/subscription/required` quando bloqueado (Inertia).

### Platform admin — assinaturas

- Na página show da org: status assinatura, plano, trial, período, histórico resumido.
- Ações: alterar plano, estender trial (+N dias), marcar `active` manual, pausar, cancelar.
- Todas auditadas em `platform_audit_logs`.

### Tenant admin — visibilidade

- Estender `/organizations/plan` com status assinatura, trial restante, próximo vencimento.
- Banner global: “Seu trial expira em X dias” / “Assinatura suspensa — regularize”.

## Tarefas técnicas

### Modelagem

- [x] Migration `subscriptions`:
  ```text
  organization_id (unique), plan_id
  status, billing_provider (manual default)
  provider_customer_id, provider_subscription_id (nullable)
  trial_ends_at, current_period_start, current_period_end
  canceled_at, cancel_at_period_end (bool)
  metadata (json)
  ```
- [x] Model `Subscription` + factory + estados helpers:
  - `isTrialing()`, `isAccessible()`, `daysUntilTrialEnds()`, `onGracePeriod()`.
- [x] Relacionamentos: `Organization::subscription()`, `Plan::subscriptions()`.

### Config

- [x] `config/docflow.php`:
  ```php
  'subscription' => [
      'default_trial_days' => 14,
      'grace_days' => 7,
      'default_plan_slug' => 'essencial',
  ],
  ```

### Actions

- [x] `App\Actions\Billing\CreateTrialSubscription` — chamado em `CreateOrganization`.
- [x] `App\Actions\Billing\ChangeOrganizationPlan` — platform admin; recalcula limites via Sprint 14.
- [x] `App\Actions\Billing\ExtendTrial` — adiciona dias a `trial_ends_at`.
- [x] `App\Actions\Billing\CancelSubscription` — imediato ou `cancel_at_period_end`.
- [x] `App\Actions\Billing\MarkSubscriptionPastDue` / `ActivateSubscription`.

### Middleware

- [x] `EnsureOrganizationAccessible` — lógica unificada org + subscription.
- [x] Registrar alias `org.accessible`.
- [x] Refatorar `EnsureOrganizationIsActive` para delegar ou deprecar em favor do novo.
- [x] Aplicar `org.accessible` em `routes/web.php` (grupo auth tenant).
- [x] Aplicar em rotas API tenant-scoped.
- [x] Portal: `EnsurePortalOrganizationAccessible` ou check em `ClientPortalController` base.

### Commands + scheduler

- [x] `subscriptions:expire-trials` — idempotente, log em `scheduler_run_logs`.
- [x] `subscriptions:apply-grace-expiry` — past_due além do grace → cancel + suspend.
- [x] Registrar em `bootstrap/app.php` (diário, ex.: 06:00).

### Controllers

- [x] `Platform\SubscriptionController` — show, changePlan, extendTrial, cancel, activate, pause.
- [x] `SubscriptionRequiredController@show` — página bloqueio (`GET /subscription/required`).
- [x] Atualizar `CreateOrganization` para criar trial subscription.

### Frontend

- [x] `Platform/Organizations/Show.vue` — seção assinatura + ações.
- [x] `Subscription/Required.vue` — mensagem + contato suporte + link plano (se admin).
- [x] `Organizations/Plan.vue` — status trial/active/past_due, datas.
- [x] `SubscriptionBanner.vue` no `AppLayout` — trial/expirado/past_due.
- [x] `HandleInertiaRequests` — compartilhar `subscription_status`, `trial_days_left`.

### Integração PlanLimitChecker

- [x] `ResolvesOrganizationPlan` passa a ler `subscription.plan_id` (fallback default se sem assinatura).
- [x] Assinatura `canceled` → bloqueio antes de checar limites.

### Testes

- [x] Feature: criar org gera subscription `trialing` com plano essencial.
- [x] Feature: org suspensa manualmente → 403 web e API.
- [x] Feature: trial expirado → redirect `/subscription/required`.
- [x] Feature: `past_due` dentro do grace → acesso OK com banner.
- [x] Feature: `past_due` após grace → bloqueio + org suspended.
- [x] Feature: platform admin estende trial → usuário volta a acessar.
- [x] Feature: platform admin change plan → `PlanLimitChecker` reflete novo plano.
- [x] Feature: portal bloqueado quando org suspensa.
- [x] Feature: commands idempotentes (rodar 2x não duplica efeitos).

## Endpoints

| Método | Rota | Quem |
|--------|------|------|
| GET | `/subscription/required` | Usuário bloqueado |
| POST | `/platform/organizations/{org}/subscription/change-plan` | Platform admin |
| POST | `/platform/organizations/{org}/subscription/extend-trial` | Platform admin |
| POST | `/platform/organizations/{org}/subscription/cancel` | Platform admin |
| POST | `/platform/organizations/{org}/subscription/activate` | Platform admin |
| POST | `/platform/organizations/{org}/subscription/pause` | Platform admin |

## Condições de aceite

- Toda org nova possui subscription em trial.
- Suspensão manual (Sprint 13) e cancelamento por billing bloqueiam web, API e portal.
- Grace period respeitado antes de suspensão automática.
- Platform admin gerencia ciclo de vida sem SQL manual.
- Banner e página de bloqueio informam claramente o motivo.
- Testes cobrem trial, grace, suspend manual e change plan.

## Fora do escopo

- Faturas e pagamentos (Sprint 16).
- Webhooks de gateway.
- E-mail transacional de trial expirando (Sprint 16 — preparar hooks).
- Upgrade self-service pelo tenant.

## Ordem sugerida de implementação

1. Migration subscription + model + CreateTrialSubscription.
2. EnsureOrganizationAccessible + rotas web/API/portal.
3. Commands expire-trials + grace + scheduler.
4. Platform subscription actions + UI.
5. Tenant banners + página required.
6. Integrar ResolvesOrganizationPlan + testes.
