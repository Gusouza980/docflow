# Sprint 04.4 — Web: Template do Sistema

## Objetivo

Criar o template principal da aplicação web com navegação, topbar, footer e área de conteúdo, preparando a base visual e estrutural para as telas internas do SaaS.

## Dependências

- Guia visual da Sprint 04.1.
- Página do guia visual da Sprint 04.2.
- Autenticação web da Sprint 04.3.
- Inertia.js e Vue.js configurados.

## Estrutura Esperada

- Sidebar/navbar principal.
- Topbar.
- Área principal de conteúdo.
- Footer discreto.
- Breadcrumbs.
- Menu do usuário.
- Seletor de organização ativa.
- Área de notificações/lembretes.
- Slot para ações de página.
- Slot para filtros ou toolbar contextual.

## Tarefas Técnicas

### Layout Vue

- Criar layout principal em `resources/js/Layouts/AppLayout.vue`.
- Criar layout de autenticação em `resources/js/Layouts/AuthLayout.vue`, se ainda não existir.
- Criar componentes de navegação reutilizáveis.
- Criar estrutura responsiva para desktop e mobile.

### Navegação principal

Incluir entradas iniciais para:

- Dashboard.
- Clientes.
- Documentos.
- Solicitações documentais.
- Tarefas.
- Prazos.
- Agenda.
- Financeiro.
- Relatórios.
- Configurações.

### Topbar

- Exibir título da página.
- Exibir breadcrumbs ou contexto atual.
- Exibir busca global preparada para implementação futura.
- Exibir seletor de organização ativa.
- Exibir notificações/lembretes.
- Exibir menu do usuário com perfil e logout.

### Sidebar/Navbar

- Exibir estado ativo da rota atual.
- Suportar colapso em desktop.
- Suportar drawer em mobile.
- Usar ícones consistentes.
- Manter áreas clicáveis adequadas.
- Evitar texto quebrado ou sobreposto.

### Main Content

- Criar container padrão.
- Suportar páginas densas com tabelas.
- Suportar páginas de formulário.
- Suportar páginas com toolbar superior.
- Suportar estados de loading e empty state.

### Footer

- Exibir informações discretas do sistema.
- Não competir visualmente com o conteúdo principal.
- Preparar área para versão do sistema, se necessário.

### Permissões e Organização

- Preparar navegação para ocultar itens conforme permissões.
- Preparar troca de organização ativa.
- Garantir que o contexto da organização ativa seja claro.

### Responsividade

- Desktop: sidebar fixa ou colapsável.
- Tablet: sidebar compacta.
- Mobile: menu em drawer.
- Garantir que topbar e conteúdo não quebrem.
- Garantir que ações primárias continuem acessíveis.

### Acessibilidade

- Botões icon-only com aria-label.
- Foco visível.
- Navegação por teclado.
- Landmarks semânticos: nav, header, main, footer.
- Contraste conforme guia.

### Testes e Validação

- Testar renderização de página interna autenticada.
- Testar redirecionamento de usuário não autenticado.
- Testar logout pelo menu do usuário.
- Testar responsividade visual em desktop e mobile.
- Rodar build frontend.
- Rodar testes backend existentes para garantir que API não foi quebrada.

## Condições de Aceite

- Existe layout principal reutilizável para páginas internas.
- Existe estrutura de navegação com sidebar/navbar, topbar, footer e main content.
- Template segue o guia visual.
- Template é responsivo.
- Menu do usuário contém logout funcional.
- Organização ativa é exibida ou há espaço preparado para isso.
- Navegação permite expansão futura dos módulos.
- Build frontend passa.
- Testes existentes continuam passando.

## Fora do Escopo

- Implementar todas as páginas internas dos módulos.
- Implementar busca global real.
- Implementar centro de notificações completo.
- Implementar troca avançada de organização, salvo estrutura inicial.
