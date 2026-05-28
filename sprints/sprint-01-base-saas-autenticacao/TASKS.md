# Sprint 01 — Base SaaS e Autenticação

## Objetivo

Construir a fundação da API SaaS: organização, usuário, vínculo de membros, autenticação, seleção de organização ativa, convites, sessões, auditoria inicial e padrões de resposta da API.

## Referências

- `docs/documento_tecnico_app_gestao_escritorios.md`
- `docs/casos_de_uso_app_gestao_escritorios.md`
- Casos de uso: UC-001 a UC-009, UC-150, UC-158.

## Escopo funcional

- Criar organização.
- Autenticar usuário.
- Selecionar organização ativa.
- Recuperar senha.
- Convidar usuário interno.
- Aceitar convite.
- Revogar acesso de usuário.
- Gerenciar sessões e dispositivos.
- Configurar dados básicos da organização.
- Registrar auditoria das ações sensíveis iniciais.

## Tarefas técnicas

### Estrutura inicial da API

- Criar versionamento de rotas em `/api/v1`.
- Definir padrão JSON para sucesso, erro de validação, erro de autorização e erro inesperado.
- Garantir que respostas de validação sigam formato previsível.
- Configurar middleware para forçar resposta JSON em rotas de API.
- Criar camada base de API Resources quando aplicável.

### Modelagem e migrations

- Criar tabela `organizations`.
- Criar tabela pivô `organization_members`.
- Ajustar ou complementar `users` para suportar uso SaaS.
- Criar tabela `organization_invitations`.
- Criar tabela `audit_logs` em versão inicial.
- Criar campos mínimos para status, papel inicial, datas de convite e aceite.
- Adicionar índices para `organization_id`, `user_id`, `email`, `status` e tokens de convite.
- Garantir foreign keys e timestamps.

### Autenticação

- Configurar autenticação por token para API.
- Implementar endpoint de login.
- Implementar endpoint de logout.
- Implementar endpoint de usuário autenticado.
- Implementar recuperação e redefinição de senha.
- Implementar revogação de sessões/tokens.
- Aplicar rate limit em login e recuperação de senha.

### Organização ativa

- Implementar listagem de organizações disponíveis para o usuário autenticado.
- Implementar seleção ou troca da organização ativa.
- Garantir que rotas protegidas exijam contexto de organização quando necessário.
- Criar helper ou serviço de contexto da organização ativa.
- Garantir que contexto seja usável por controllers, policies, jobs e auditoria.

### Convites e membros

- Implementar criação de convite.
- Implementar aceite de convite por usuário novo ou existente.
- Implementar cancelamento ou expiração de convite.
- Implementar suspensão e reativação de membro.
- Impedir remoção ou suspensão do último administrador ativo da organização.

### Auditoria inicial

- Criar serviço/action para registrar eventos auditáveis.
- Registrar criação de organização.
- Registrar login relevante quando aplicável.
- Registrar convite, aceite, revogação e alteração de organização.
- Registrar IP, user agent, usuário, organização, recurso e ação quando disponíveis.

### Segurança e autorização

- Criar policies iniciais para organização, membro e convite.
- Garantir que usuário não consiga acessar organização da qual não participa.
- Garantir que usuário inativo ou suspenso não consiga operar na organização.
- Mascarar dados sensíveis quando o usuário tiver permissão parcial.

### Testes

- Testar criação de organização com usuário administrador.
- Testar login com credenciais válidas e inválidas.
- Testar rate limit de login.
- Testar troca de organização ativa.
- Testar convite, aceite e expiração.
- Testar bloqueio de acesso a organização de outro usuário.
- Testar que último administrador não pode ser removido.
- Testar auditoria das principais ações.

## Endpoints esperados

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/forgot-password`
- `POST /api/v1/auth/reset-password`
- `GET /api/v1/organizations`
- `POST /api/v1/organizations`
- `PATCH /api/v1/organizations/{organization}`
- `POST /api/v1/organizations/{organization}/switch`
- `GET /api/v1/organization-members`
- `POST /api/v1/organization-invitations`
- `POST /api/v1/organization-invitations/{token}/accept`
- `DELETE /api/v1/organization-invitations/{invitation}`
- `PATCH /api/v1/organization-members/{member}/suspend`
- `PATCH /api/v1/organization-members/{member}/reactivate`

## Condições de aceite

- Um usuário consegue criar uma organização e se torna administrador dela.
- Um usuário autenticado só consegue operar dentro de organizações às quais pertence.
- Rotas protegidas não funcionam sem token válido.
- Organização ativa é exigida em rotas que dependem de contexto SaaS.
- Convites expiram e não podem ser aceitos mais de uma vez.
- O sistema impede remover ou suspender o último administrador da organização.
- Membros suspensos perdem acesso imediatamente.
- Ações sensíveis geram registros em `audit_logs`.
- Respostas da API seguem padrão único para sucesso, validação e autorização.
- Testes automatizados cobrem os fluxos principais e falhas críticas.

## Fora do escopo

- Matriz completa de permissões por módulo.
- Cadastro completo de clientes.
- Cobranças, documentos, tarefas e relatórios.
- Integrações externas reais.

