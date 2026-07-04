# Sprint 13 — Fundação platform admin e gestão de tenants

## Objetivo

Criar a camada operacional **Docflow → tenants**: autenticação de platform admin, painel `/platform`, listagem e gestão de organizações (suspender/reativar), auditoria cross-tenant e base para planos/assinaturas nas sprints seguintes.

## Referências

- [Horizonte 2 — README](../horizonte-02-platform-billing/README.md)
- `app/Models/Organization.php`
- `app/Http/Controllers/Web/AuditController.php` (padrão de listagem admin)
- `app/Actions/Organizations/RecordAuditLog.php`
- `database/seeders/PermissionSeeder.php` — **não** usar `super-admin` como platform admin

## Pré-requisitos

- Horizonte 1 concluído (auth web, Inertia, organizações, auditoria por tenant).

## Escopo funcional

### Acesso platform admin

- Usuário com flag `is_platform_admin` acessa `/platform` após login web normal.
- Usuário comum e admin de tenant **não** acessam rotas `/platform` (403).
- Layout dedicado (sidebar própria, sem contexto de organização ativa).

### Gestão de tenants

- Listar organizações: nome, documento, status, data de criação, contagem de membros/clientes.
- Filtrar por status (`active`, `suspended`) e busca por nome/documento.
- Detalhe da organização: dados cadastrais, membros ativos, métricas resumidas (clientes, storage se disponível).
- Suspender organização: `status → suspended`, motivo opcional, audit platform.
- Reativar organização: `status → active`, audit platform.
- Notas internas da plataforma sobre a org (`platform_notes` text nullable).

### Auditoria platform

- Registrar ações: login platform, suspend, reactivate, view tenant detail, alteração de notas.
- Tabela separada `platform_audit_logs` (não misturar com `audit_logs` por org).

## Tarefas técnicas

### Modelagem

- [ ] Migration: `users.is_platform_admin` boolean default false.
- [ ] Migration: `organizations.platform_notes` text nullable.
- [ ] Migration: `platform_audit_logs`:
  - `platform_admin_user_id`, `action`, `subject_type`, `subject_id`, `metadata` JSON, `ip_address`, `user_agent`, timestamps.
- [ ] Model `PlatformAuditLog` + factory mínima.

### Autorização

- [ ] Middleware `EnsurePlatformAdmin`: exige `auth` + `user->is_platform_admin`.
- [ ] Registrar alias em `bootstrap/app.php`: `platform.admin`.
- [ ] **Não** usar Spatie `super-admin` para este fluxo (documentar no código).

### Actions / services

- [ ] `App\Actions\Platform\RecordPlatformAuditLog` — espelho de `RecordAuditLog`, sem `organization_id` obrigatório.
- [ ] `App\Actions\Platform\SuspendOrganization` — update status + audit + metadata (motivo).
- [ ] `App\Actions\Platform\ReactivateOrganization` — update status + audit.
- [ ] `App\Support\PlatformOrganizationMetrics` — contagens: members, clients, receivables open (opcional), último login (opcional fase 2).

### Controllers e rotas

- [ ] `Platform\DashboardController@index` — cards: total orgs, ativas, suspensas.
- [ ] `Platform\OrganizationController@index` — listagem paginada + filtros.
- [ ] `Platform\OrganizationController@show` — detalhe + métricas + notas.
- [ ] `Platform\OrganizationController@updateNotes` — PATCH notas internas.
- [ ] `Platform\OrganizationController@suspend` / `@reactivate` — POST.
- [ ] Grupo de rotas em `routes/web.php`:
  ```php
  Route::prefix('platform')->middleware(['auth', 'platform.admin'])->name('platform.')->group(...)
  ```

### Frontend (Inertia)

- [ ] Layout `PlatformLayout.vue` — nav: Dashboard, Organizações, (placeholder Planos/Assinaturas).
- [ ] `Platform/Dashboard/Index.vue` — KPIs globais.
- [ ] `Platform/Organizations/Index.vue` — tabela, filtros, badges de status.
- [ ] `Platform/Organizations/Show.vue` — detalhe, ações suspender/reativar, formulário de notas.
- [ ] Modal de confirmação para suspender (motivo obrigatório ou opcional — definir no kickoff).

### Seeders

- [ ] `PlatformAdminSeeder` ou extensão de `DemoWorkspaceSeeder`: usuário `platform@docflow.test` com `is_platform_admin = true`.
- [ ] Documentar credenciais em comentário do seeder (não commitar senha real).

### Enforcement inicial (preparação Sprint 15)

- [ ] Extrair checagem `Organization::STATUS_ACTIVE` para helper `Organization::isOperational()` (suspensa = false).
- [ ] **Não** bloquear web ainda — apenas preparar helper; enforcement completo na Sprint 15.

### Testes

- [ ] Feature: platform admin acessa `/platform` → 200.
- [ ] Feature: admin de tenant acessa `/platform` → 403.
- [ ] Feature: assistente acessa `/platform` → 403.
- [ ] Feature: suspender org altera `status` e grava `platform_audit_logs`.
- [ ] Feature: reativar org restaura `status active`.
- [ ] Feature: atualizar `platform_notes` auditado.
- [ ] Feature: listagem paginada retorna orgs de múltiplas orgs (cross-tenant isolado ao controller platform).

## Endpoints

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/platform` | Dashboard platform |
| GET | `/platform/organizations` | Listagem tenants |
| GET | `/platform/organizations/{organization}` | Detalhe tenant |
| PATCH | `/platform/organizations/{organization}/notes` | Notas internas |
| POST | `/platform/organizations/{organization}/suspend` | Suspender |
| POST | `/platform/organizations/{organization}/reactivate` | Reativar |

## Condições de aceite

- Platform admin navega painel sem selecionar organização ativa.
- Suspender/reactivar reflete imediatamente no banco e na auditoria platform.
- Nenhuma rota `/platform` vaza dados sem middleware `platform.admin`.
- Layout platform visualmente distinto do app tenant (evitar confusão operacional).
- Testes cobrem 403 para não-admins e fluxo suspend/reactivate.

## Fora do escopo

- Planos, assinaturas, limites (Sprints 14–15).
- Bloqueio automático web/API ao suspender (Sprint 15).
- Impersonação de usuário tenant.
- API REST `/api/v1/platform/*` (opcional sprint futura).
- MFA obrigatório (fase 2).

## Ordem sugerida de implementação

1. Migrations + models + middleware.
2. Actions suspend/reactivate + platform audit.
3. Controllers + rotas.
4. Layout + páginas Inertia.
5. Seeder platform admin + testes.
