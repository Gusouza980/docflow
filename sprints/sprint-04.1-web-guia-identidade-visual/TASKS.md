# Sprint 04.1 — Web: Guia de Identidade Visual

## Objetivo

Definir o guia de identidade visual da aplicação web acoplada ao Laravel, criando uma base consistente para UI/UX, componentes, linguagem visual, acessibilidade e experiência operacional do sistema.

## Contexto

O sistema deixará de ser apenas API para também entregar a aplicação web no mesmo projeto Laravel. A interface web será implementada com Inertia.js e Vue.js, enquanto o aplicativo mobile continuará externo consumindo a API.

Esta subsprint deve produzir o guia visual antes da implementação das páginas, evitando inconsistência visual, decisões improvisadas e retrabalho na criação dos componentes.

## Referências

- `docs/briefing_app_gestao_escritorios.md`
- `docs/documento_tecnico_app_gestao_escritorios.md`
- `docs/casos_de_uso_app_gestao_escritorios.md`
- Sprints já implementadas: SaaS, clientes, documentos, tarefas, prazos e agenda.

## Direção de Design

### Personalidade visual

- Profissional, confiável e discreta.
- Visual limpo, operacional e focado em produtividade.
- Interface densa o suficiente para uso diário por escritórios, mas sem parecer pesada.
- Evitar aparência de landing page, excesso de ilustração, gradientes decorativos e elementos puramente estéticos.
- Priorizar hierarquia clara, leitura rápida, ações previsíveis e feedback imediato.

### Público-alvo

- Escritórios de advocacia.
- Escritórios contábeis.
- Consultórios e empresas de serviços profissionais.
- Equipes administrativas e financeiras que usam o sistema repetidamente durante o dia.

## Tarefas Técnicas

### Documento do guia

- Criar documento em `docs/guia_identidade_visual_web.md`.
- Documentar princípios visuais e de UX.
- Documentar decisões de layout, espaçamento, tipografia, cores, componentes e estados.
- Documentar padrões de escrita e microcopy.
- Documentar regras de responsividade.
- Documentar regras de acessibilidade.

### Paleta de cores

Definir tokens semânticos para:

- Cor primária.
- Cor secundária.
- Cor de destaque.
- Background da aplicação.
- Background de superfícies.
- Bordas e divisores.
- Texto principal.
- Texto secundário.
- Texto desabilitado.
- Estados: sucesso, aviso, erro, informação.
- Estados interativos: hover, active, focus, disabled.
- Estados operacionais: atrasado, pendente, aprovado, recusado, concluído, crítico.

### Tipografia

- Definir fonte principal para interface.
- Definir fonte alternativa segura.
- Definir escala de títulos, subtítulos, corpo, labels, captions e textos auxiliares.
- Definir pesos, line-height e regras de truncamento.
- Definir padrão de números, moedas, datas, prazos e documentos.

### Layout e espaçamento

- Definir escala de espaçamento.
- Definir grid principal.
- Definir largura de container para áreas internas.
- Definir padrões de densidade para dashboards, tabelas e formulários.
- Definir comportamento desktop, tablet e mobile.

### Componentes base

Definir aparência, uso correto e estados de:

- Botões primários, secundários, ghost, danger e icon-only.
- Inputs de texto.
- Textareas.
- Selects.
- Combobox/autocomplete.
- Date picker.
- Currency input.
- Search input.
- Checkboxes.
- Radios.
- Toggles.
- Badges.
- Status pills.
- Cards de entidades.
- Tabelas.
- Tabs.
- Breadcrumbs.
- Dropdowns.
- Menus contextuais.
- Modals.
- Drawers.
- Empty states.
- Loading states.
- Skeletons.
- Tooltips.
- Toasts.
- Alerts inline.
- Paginação.
- Avatares.
- Indicadores de prioridade e risco.

### Navegação

- Definir padrão de sidebar/navbar.
- Definir topbar.
- Definir breadcrumbs.
- Definir menu de usuário.
- Definir troca de organização ativa.
- Definir comportamento em telas pequenas.
- Definir estados ativo, hover e colapsado.

### Formulários

- Definir layout padrão de formulário.
- Definir labels, hints, erros e campos obrigatórios.
- Definir agrupamento de seções.
- Definir botões de ação.
- Definir comportamento de validação.
- Definir UX para salvar, cancelar, descartar alterações e estados de envio.

### Tabelas e listagens

- Definir padrão para filtros.
- Definir busca.
- Definir ordenação.
- Definir ações por linha.
- Definir bulk actions para uso futuro.
- Definir estado vazio, erro e carregamento.
- Definir padrão de colunas para entidades operacionais.

### Feedback e estados

- Definir padrões de toast.
- Definir padrões de confirmação.
- Definir mensagens de erro.
- Definir mensagens de sucesso.
- Definir estados de permissões insuficientes.
- Definir estados offline ou falha de rede para uso futuro.

### Iconografia

- Definir biblioteca de ícones.
- Preferir `lucide-vue-next`.
- Definir tamanho padrão de ícones.
- Definir uso de ícones em botões, navegação, status e ações.

### Acessibilidade

- Definir contraste mínimo.
- Definir foco visível.
- Definir navegação por teclado.
- Definir uso de aria-label em botões icon-only.
- Definir mensagens de erro associadas aos campos.
- Definir tamanhos mínimos de área clicável.

### Tokens e implementação futura

- Preparar nomes de tokens CSS/Tailwind para uso na aplicação.
- Definir padrão para modo claro inicialmente.
- Documentar como um modo escuro poderia ser adicionado depois sem implementar agora.

## Condições de Aceite

- Existe um documento completo em `docs/guia_identidade_visual_web.md`.
- O guia define paleta, tipografia, espaçamento, componentes, formulários, tabelas, navegação, toasts, dropdowns e estados.
- O guia é coerente com uma aplicação SaaS operacional.
- O guia evita decisões puramente decorativas que prejudiquem produtividade.
- O guia permite implementar componentes Vue reutilizáveis sem novas decisões visuais importantes.
- O guia contempla acessibilidade básica e responsividade.

## Fora do Escopo

- Implementar os componentes Vue.
- Criar a página visual do guia.
- Implementar autenticação.
- Implementar template navegável.
