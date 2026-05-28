# Sprint 04.3 — Web: Autenticação com Inertia e Vue

## Objetivo

Criar as páginas web de autenticação do sistema usando Inertia.js e Vue.js, integradas ao backend Laravel existente, preservando a API mobile externa e evitando conflito entre autenticação web por sessão e autenticação mobile/API por Sanctum token.

## Dependências

- Inertia.js e Vue.js configurados.
- Guia visual da Sprint 04.1 concluído.
- Página do guia visual da Sprint 04.2 criada ou em andamento.
- Fluxos backend de autenticação já existentes para API.

## Decisão Técnica

### Web

- Usar autenticação web baseada em sessão/cookie.
- Usar middleware `web`.
- Usar CSRF padrão do Laravel.
- Usar Inertia para renderizar telas.

### Mobile/API

- Manter autenticação por Sanctum token nas rotas `/api/v1`.
- Não quebrar contratos já criados para o mobile.
- Não reutilizar diretamente endpoints JSON da API para login web se isso comprometer UX ou sessão.

## Páginas Esperadas

- Login.
- Esqueci minha senha.
- Redefinir senha.
- Aceitar convite, se aplicável ao fluxo web.
- Estado de sessão expirada.

## Rotas Esperadas

Sugestão:

- `GET /login`
- `POST /login`
- `POST /logout`
- `GET /forgot-password`
- `POST /forgot-password`
- `GET /reset-password/{token}`
- `POST /reset-password`
- `GET /invitations/{token}/accept`
- `POST /invitations/{token}/accept`

## Tarefas Técnicas

### Backend

- Criar controllers web de autenticação, se ainda não existirem.
- Separar claramente controllers web dos controllers API.
- Validar credenciais com Form Requests ou validação dedicada.
- Regenerar sessão no login.
- Invalidar sessão no logout.
- Preservar proteção CSRF.
- Aplicar rate limiting em login e recuperação de senha.
- Redirecionar usuário autenticado para dashboard/home.
- Redirecionar usuário não autenticado para login.

### Inertia Shared Props

- Compartilhar usuário autenticado.
- Compartilhar organização ativa, quando disponível.
- Compartilhar flash messages.
- Compartilhar erros de validação.
- Compartilhar permissões mínimas necessárias para navegação futura.

### Páginas Vue

- Criar tela de login com layout profissional.
- Criar tela de recuperação de senha.
- Criar tela de redefinição de senha.
- Criar tela de aceite de convite, se aplicável.
- Usar componentes e tokens definidos no guia visual.
- Garantir UX clara para carregamento, erro, sucesso e validação.

### Formulários

- Usar `useForm` do Inertia.
- Exibir erros por campo.
- Desabilitar botão durante envio.
- Evitar duplo submit.
- Preservar e-mail após falha quando apropriado.

### Segurança

- Não expor mensagens que permitam enumeração de usuários.
- Manter rate limit no login.
- Regenerar sessão no login.
- Invalidar sessão e token CSRF no logout.
- Garantir que rotas autenticadas exigem sessão web.

### Testes

- Testar renderização da página de login.
- Testar login com credenciais válidas.
- Testar bloqueio com credenciais inválidas.
- Testar logout.
- Testar recuperação de senha.
- Testar redefinição de senha.
- Testar que rotas API continuam funcionando com Sanctum token.

## Condições de Aceite

- Usuário consegue acessar login web.
- Usuário consegue autenticar via sessão.
- Usuário autenticado é redirecionado para área interna.
- Usuário consegue sair da sessão web.
- Fluxo de esqueci minha senha funciona.
- Fluxo de redefinição de senha funciona.
- Telas seguem o guia visual.
- Erros de validação são claros.
- API mobile continua intacta.
- Testes cobrem login, logout e recuperação de senha.

## Fora do Escopo

- Criar dashboard completo.
- Criar template final completo, salvo estrutura mínima necessária.
- Implementar MFA.
- Implementar SSO.
- Implementar cadastro público de usuários.
