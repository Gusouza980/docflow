# Sprint 04.2 — Web: Página do Guia de Identidade Visual

## Objetivo

Criar uma rota web com uma página Inertia/Vue que apresente visualmente o Guia de Identidade Visual da aplicação, servindo como referência viva para desenvolvimento dos componentes e validação visual.

## Dependências

- Sprint 04.1 concluída.
- Documento `docs/guia_identidade_visual_web.md` criado.
- Inertia.js e Vue.js instalados/configurados no projeto Laravel.
- Build frontend funcional com Vite.

## Tarefas Técnicas

### Estrutura Inertia/Vue

- Confirmar estrutura de `resources/js`.
- Confirmar entrada principal do Inertia.
- Confirmar configuração do Vite.
- Criar rota web para o guia.
- Usar rota protegida ou temporariamente pública conforme decisão do projeto.
- Nome sugerido: `GET /style-guide`.

### Página

- Criar página Vue para o guia visual.
- Nome sugerido: `resources/js/Pages/StyleGuide/Index.vue`.
- Organizar a página em seções escaneáveis.
- Usar layout simples ou layout específico de documentação, sem depender ainda do template final da aplicação.

### Conteúdo visual obrigatório

Exibir amostras reais de:

- Paleta de cores.
- Tokens semânticos.
- Tipografia.
- Escala de espaçamento.
- Botões.
- Inputs.
- Textareas.
- Selects.
- Checkboxes.
- Toggles.
- Badges.
- Status pills.
- Alerts.
- Toasts demonstrativos.
- Dropdowns.
- Tabs.
- Cards.
- Tabelas.
- Modals demonstrativos.
- Empty states.
- Loading/skeleton states.
- Navbar/sidebar demonstrativa.
- Topbar demonstrativa.

### Componentização inicial

- Criar apenas componentes necessários para a página do guia, quando isso evitar repetição real.
- Evitar criar uma biblioteca completa antes da validação visual.
- Manter componentes em local que possa evoluir para `resources/js/Components`.

### Responsividade

- Garantir que a página funcione em desktop, tablet e mobile.
- Garantir que textos não estourem containers.
- Garantir que tabelas tenham comportamento responsivo adequado.

### Acessibilidade

- Garantir foco visível em elementos interativos.
- Garantir labels em campos.
- Garantir aria-label em botões somente com ícone.
- Garantir contraste conforme guia.

### Validação visual

- Rodar build frontend.
- Abrir a página localmente.
- Verificar a página em desktop e mobile.
- Corrigir quebras visuais, sobreposição de textos e inconsistências.

## Condições de Aceite

- Existe rota acessível para o guia visual.
- A página é renderizada por Inertia/Vue.
- A página apresenta todos os elementos principais do guia.
- A página usa os tokens e decisões definidas na Sprint 04.1.
- A página é responsiva.
- Não há textos sobrepostos ou elementos quebrados em mobile e desktop.
- O build frontend passa.
- A página pode ser usada como referência por desenvolvedores durante as próximas sprints.

## Fora do Escopo

- Implementar autenticação.
- Implementar o template final do sistema.
- Criar todas as variações futuras de componentes.
- Integrar dados reais da API.
