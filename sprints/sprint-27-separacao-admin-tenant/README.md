# Sprint 27 — Separação admin/tenant e impersonação

## Objetivo

Separar os três mundos do Docflow (admin da plataforma, app do escritório e portal do cliente) com logins, guards e prefixos de URL distintos. Cadastro de tenant só no `/admin`. Impersonação real do usuário dono a partir do painel admin.

## Decisões travadas

| # | Decisão | Escolha |
|---|---------|---------|
| 1 | Cadastro de tenant | Só platform admin em `/admin` |
| 2 | Impersonação | Entrar como o dono (membership admin mais antiga) |
| 3 | URLs | `/admin` (plataforma) e `/plataforma` (escritório) |
| 4 | Guards | `admin`, `web`, `portal` (mesmo `User` em admin/web) |
| 5 | Portal do cliente | Sem mudanças de prefixo |

## Referências

- [ACTIVE.md](../ACTIVE.md)
- `routes/web.php`
- `app/Actions/Platform/ProvisionTenant.php`
- `app/Http/Middleware/EnsurePlatformAdmin.php`
