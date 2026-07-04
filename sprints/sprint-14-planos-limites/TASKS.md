# Sprint 14 — Catálogo de planos e engine de limites

## Objetivo

Implementar **planos comerciais** (Essencial, Profissional, Escritório) com limites e features em JSON, engine de verificação de uso, enforcement em pontos críticos e UI de consulta de plano/uso para admin da organização.

## Referências

- [Horizonte 2 — README](../horizonte-02-platform-billing/README.md)
- [Sprint 13](../sprint-13-platform-admin-fundacao/TASKS.md)
- `docs/briefing_app_gestao_escritorios.md` — seção 19
- `config/docflow.php` — padrão de config centralizada

## Pré-requisitos

- Sprint 13 (painel platform para CRUD de planos ou seed + listagem read-only).

## Escopo funcional

### Catálogo de planos

- Três planos públicos alinhados ao briefing: **Essencial**, **Profissional**, **Escritório**.
- Cada plano define:
  - **Limites numéricos:** `max_members`, `max_clients`, `max_storage_mb`, `max_portal_accesses`.
  - **Features booleanas:** `portal`, `finance_advanced`, `reports_scheduling`, `audit`, `automations`, etc.
- Planos versionáveis: `is_active`, `is_public`, `sort_order`.
- Platform admin gerencia planos em `/platform/plans`.

### Overrides por organização

- Platform admin pode conceder exceção temporária (`organization_plan_overrides`): limites/features extras, `reason`, `expires_at`.
- Override expirado deixa de aplicar (job ou check lazy).

### Engine de limites

- Serviço `PlanLimitChecker` resolve plano efetivo: override vigente > plano da assinatura (Sprint 15) > plano default.
- Contadores de uso em tempo real (query) ou cache com TTL curto.
- Resposta padronizada ao exceder limite: HTTP 422 `{ code: plan_limit_exceeded, metric, limit, current }`.

### Enforcement (primeiros pontos)

- Convidar membro → `max_members`.
- Criar cliente → `max_clients`.
- Criar `ClientPortalAccess` → `max_portal_accesses` + feature `portal`.
- Upload de documento → `max_storage_mb` (somar tamanho dos arquivos da org).
- Acessar `/audit` → feature `audit` (Escritório).

### UI tenant

- Página `/organizations/plan` (admin da org): plano atual, limites, uso, features incluídas.
- Banner quando uso ≥ 80% de um limite.
- Modal/toast ao bater limite com CTA “Falar com suporte” ou link platform (sem checkout ainda).

## Tarefas técnicas

### Modelagem

- [ ] Migration `plans`:
  ```text
  slug (unique), name, description
  price_cents, billing_interval (month|year)
  trial_days, limits (json), features (json)
  is_public, is_active, sort_order
  ```
- [ ] Migration `organization_plan_overrides`:
  ```text
  organization_id, limits (json nullable), features (json nullable)
  reason, expires_at nullable, created_by_user_id
  ```
- [ ] Models `Plan`, `OrganizationPlanOverride` + factories.
- [ ] Enum ou constants `PlanFeature`, `PlanLimit` em `App\Enums` ou `config/docflow.php`.

### Config

- [ ] Estender `config/docflow.php`:
  ```php
  'plan_features' => [...labels para UI...],
  'plan_limits' => [...labels e unidades...],
  'default_plan_slug' => 'essencial',
  ```

### Seeders

- [ ] `PlanSeeder` com Essencial / Profissional / Escritório (valores do briefing):
  | Plano | max_members | max_clients | max_storage_mb | portal | audit |
  |-------|-------------|-------------|----------------|--------|-------|
  | Essencial | 3 | 50 | 2048 | false | false |
  | Profissional | 15 | 300 | 20480 | true | false |
  | Escritório | 50 | -1 (ilimitado) | 102400 | true | true |
- [ ] Registrar no `DatabaseSeeder`.

### Services

- [ ] `App\Support\Billing\ResolvesOrganizationPlan` — plano efetivo + merge override.
- [ ] `App\Support\Billing\OrganizationUsage` — métodos: `membersCount()`, `clientsCount()`, `storageMb()`, `portalAccessesCount()`.
- [ ] `App\Support\Billing\PlanLimitChecker`:
  - `assertWithinLimit(Organization, metric)` → void ou exception.
  - `usageSummary(Organization)` → array para UI.
  - `hasFeature(Organization, feature)` → bool.
- [ ] Exception `PlanLimitExceededException` → handler JSON/Inertia friendly.

### Integração nos fluxos existentes

- [ ] `InviteOrganizationMember` / store convite — checar `max_members`.
- [ ] `ClientController@store` (web + API se existir) — checar `max_clients`.
- [ ] Criação de portal access — checar `max_portal_accesses` + feature `portal`.
- [ ] Upload documento — checar storage (hook no action de upload existente).
- [ ] `AuditController` — checar feature `audit` antes de renderizar.

### Platform admin UI

- [ ] `Platform\PlanController` — CRUD planos (index, create, edit; destroy soft via `is_active`).
- [ ] `Platform\OrganizationPlanOverrideController` — criar/revogar override na página show da org.
- [ ] Páginas Vue: `Platform/Plans/Index.vue`, `Form.vue`; seção overrides em `Organizations/Show.vue`.

### Tenant UI

- [ ] `OrganizationPlanController@show` — `GET /organizations/plan`.
- [ ] `Organizations/Plan.vue` — tabela limites vs uso, lista de features, badge do plano.
- [ ] Componente `PlanUsageBanner.vue` — exibir no `AppLayout` quando ≥ 80% ou feature missing.
- [ ] Compartilhar `plan_summary` via `HandleInertiaRequests` (admin only).

### Testes

- [ ] Unit: `PlanLimitChecker` merge override + expiração.
- [ ] Unit: `OrganizationUsage` contagens corretas.
- [ ] Feature: convite bloqueado ao atingir `max_members`.
- [ ] Feature: criar cliente bloqueado ao atingir `max_clients`.
- [ ] Feature: portal access bloqueado sem feature `portal`.
- [ ] Feature: assistente não acessa `/organizations/plan` (403 — admin only).
- [ ] Feature: platform admin cria override e org passa a aceitar mais membros.

## Endpoints

| Método | Rota | Quem |
|--------|------|------|
| GET/POST/PATCH | `/platform/plans` | Platform admin |
| POST/DELETE | `/platform/organizations/{org}/overrides` | Platform admin |
| GET | `/organizations/plan` | Admin da org |

## Condições de aceite

- Três planos seedados e editáveis pelo platform admin.
- Limites enforced em membros, clientes, portal e storage.
- Features gated (`audit`, `portal`) retornam 403 ou redirect com mensagem clara.
- Admin da org vê consumo vs limites na página Plano e Uso.
- Overrides temporários funcionam e expiram.
- Testes cobrem bloqueio e override.

## Fora do escopo

- Assinatura, trial, billing (Sprint 15–16).
- Checkout ou upgrade self-service.
- Pro-rata ou alteração automática de plano.
- Contagem de API calls ou WhatsApp messages.

## Ordem sugerida de implementação

1. Migrations + models + config + PlanSeeder.
2. `OrganizationUsage` + `PlanLimitChecker`.
3. Enforcement em 4 pontos (membros, clientes, portal, storage).
4. Platform CRUD planos + overrides.
5. UI tenant plano/uso + banner.
6. Testes.
