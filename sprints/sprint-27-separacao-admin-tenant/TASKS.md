# Sprint 27 — Separação admin/tenant e impersonação

## Objetivo

Isolar o painel do dono da plataforma (`/admin`) do app do escritório (`/plataforma`), com logins separados, cadastro de tenant só no admin, e impersonação do usuário dono.

## Decisões travadas

| # | Decisão | Escolha | Por quê |
|---|---------|---------|---------|
| 1 | Quem cadastra tenant | Só platform admin | Self-service misturava o dono da plataforma com o escritório |
| 2 | Impersonar | Como o dono (admin mais antigo) | Ver exatamente o que o cliente vê |
| 3 | Prefixo admin | `/admin` | Separação visual e de bookmark |
| 4 | Prefixo tenant | `/plataforma` | Não colide com `/portal` do cliente |
| 5 | Guard admin | Sessão `admin` no mesmo `User` | Espelha o padrão do portal |

## Tarefas técnicas

- [x] Guard `admin`, login `/admin/login`, rotas `admin.*`, redirects `/platform` → `/admin`
- [x] Prefixo `/plataforma`, login do escritório, redirects das URLs raiz
- [x] Remover criação de org no web/API do tenant; membership obrigatória
- [x] Impersonar dono, banner, stop, auditoria, sessão admin preservada
- [x] Fechar vazamentos (API, guides, middleware) + testes/Pint

## Critérios de aceite

- [x] Platform admin entra em `/admin/login` e permanece em `/admin`
- [x] Escritório entra em `/plataforma/login` e permanece em `/plataforma`
- [x] Platform admin não cria org pelo app do tenant; só provisiona em `/admin`
- [x] “Acessar como dono” abre `/plataforma` como o owner, com banner para sair
- [x] Sair da impersonação volta ao `/admin` com o admin ainda autenticado
- [x] Tenant sem membership vê tela de convite, não formulário de criar org
- [x] Portal do cliente (`/portal/login`, `/client-portal`) inalterado

## Fora de escopo

- Subdomínio, MFA, TTL curto de impersonate
- Impersonar membro específico (além do dono)
- Ziggy
- Banco/schema por tenant
