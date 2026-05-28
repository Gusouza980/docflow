# Guia de Identidade Visual Web — Docflow

## 1. Objetivo

Este documento define a identidade visual e os padrões de UI/UX da aplicação web do Docflow, que será desenvolvida no próprio projeto Laravel usando Inertia.js e Vue.js.

O guia deve orientar a criação das páginas, layouts e componentes para que a interface seja consistente, profissional, acessível e adequada ao uso diário por escritórios de advocacia, contabilidade, consultorias e serviços profissionais.

## 2. Princípios de Design

### 2.1 Direção visual

A interface deve transmitir:

- Confiança.
- Organização.
- Clareza operacional.
- Segurança no tratamento de dados.
- Produtividade para uso diário.
- Profissionalismo sem aparência excessivamente corporativa ou fria.

A aplicação não deve parecer uma landing page. O produto é uma ferramenta de trabalho, então o design deve priorizar leitura rápida, densidade equilibrada, navegação previsível, formulários claros e tabelas eficientes.

### 2.2 Regras gerais

- Usar elementos visuais com função clara.
- Evitar gradientes decorativos, ilustrações genéricas, cards em excesso e fundos chamativos.
- Evitar telas muito vazias em áreas operacionais.
- Priorizar hierarquia, alinhamento e espaçamento em vez de decoração.
- Usar cores fortes apenas para ações, estados e alertas importantes.
- Manter consistência entre módulos: clientes, documentos, tarefas, prazos, agenda e financeiro.
- Nunca depender apenas de cor para comunicar status; combinar cor com texto, ícone ou formato.

## 3. Personalidade da Marca

### 3.1 Atributos

- Profissional.
- Organizada.
- Precisa.
- Humana.
- Discreta.
- Moderna.
- Confiável.

### 3.2 Tom visual

O visual deve ser claro e calmo, com contraste suficiente e pontos de destaque bem controlados. A sensação desejada é de uma mesa de trabalho bem organizada: tudo tem lugar, tudo é fácil de encontrar e as pendências são evidentes.

### 3.3 Evitar

- Paletas muito monocromáticas.
- Excesso de azul escuro ou roxo.
- Visual de sistema bancário pesado.
- Visual lúdico demais.
- Bordas muito arredondadas.
- Sombras fortes.
- Componentes grandes sem necessidade.
- Textos longos dentro de botões ou badges.

## 4. Paleta de Cores

### 4.1 Estratégia

A paleta usa uma base neutra quente-fria para áreas operacionais, uma cor primária sóbria para navegação e ações principais, uma cor secundária para apoio visual e cores semânticas para estados.

A aplicação deve ser majoritariamente clara. O modo escuro pode ser planejado no futuro, mas não deve ser implementado nesta etapa.

## 4.2 Tokens principais

| Token | Hex | Uso |
|---|---:|---|
| `brand.primary` | `#2563EB` | Ações primárias, links importantes, foco institucional |
| `brand.primary.hover` | `#1D4ED8` | Hover de ação primária |
| `brand.primary.active` | `#1E40AF` | Active de ação primária |
| `brand.secondary` | `#0F766E` | Destaques secundários, indicadores de organização |
| `brand.secondary.hover` | `#0D665F` | Hover secundário |
| `brand.accent` | `#D97706` | Destaques pontuais, avisos leves e pontos de atenção |

### 4.3 Neutros

| Token | Hex | Uso |
|---|---:|---|
| `neutral.0` | `#FFFFFF` | Superfícies elevadas, inputs, cards |
| `neutral.25` | `#FCFCFD` | Background alternativo sutil |
| `neutral.50` | `#F8FAFC` | Background principal da aplicação |
| `neutral.100` | `#F1F5F9` | Background de seções, hover leve |
| `neutral.200` | `#E2E8F0` | Bordas padrão |
| `neutral.300` | `#CBD5E1` | Bordas fortes, disabled |
| `neutral.400` | `#94A3B8` | Texto auxiliar fraco |
| `neutral.500` | `#64748B` | Texto secundário |
| `neutral.600` | `#475569` | Texto de apoio |
| `neutral.700` | `#334155` | Texto forte |
| `neutral.800` | `#1E293B` | Títulos e navegação |
| `neutral.900` | `#0F172A` | Texto principal de alta ênfase |

### 4.4 Estados semânticos

| Estado | Background | Texto/Borda | Uso |
|---|---:|---:|---|
| Sucesso | `#ECFDF5` | `#047857` | Concluído, aprovado, pagamento recebido |
| Informação | `#EFF6FF` | `#2563EB` | Dicas, contexto, sincronização |
| Aviso | `#FFFBEB` | `#B45309` | Pendente, vencimento próximo, atenção |
| Erro | `#FEF2F2` | `#DC2626` | Recusado, falha, bloqueio |
| Neutro | `#F8FAFC` | `#475569` | Rascunho, inativo, sem prioridade |

### 4.5 Estados operacionais

| Token | Background | Texto/Borda | Uso |
|---|---:|---:|---|
| `status.pending` | `#FFFBEB` | `#B45309` | Pendente, solicitado, em aberto |
| `status.in_progress` | `#EFF6FF` | `#2563EB` | Em andamento, recebido, em análise |
| `status.completed` | `#ECFDF5` | `#047857` | Concluído, aprovado, pago |
| `status.rejected` | `#FEF2F2` | `#DC2626` | Recusado, rejeitado, falhou |
| `status.overdue` | `#FFF1F2` | `#BE123C` | Atrasado, vencido |
| `status.cancelled` | `#F1F5F9` | `#64748B` | Cancelado, arquivado |
| `status.critical` | `#FEF2F2` | `#B91C1C` | Crítico, alto risco |

### 4.6 Foco, seleção e interação

- Focus ring: `#93C5FD`, 2px, com offset de 2px.
- Hover de superfície: `#F8FAFC`.
- Item selecionado: background `#EFF6FF`, borda `#BFDBFE`, texto `#1D4ED8`.
- Disabled background: `#F1F5F9`.
- Disabled text: `#94A3B8`.

## 5. Tipografia

### 5.1 Fonte principal

Fonte recomendada:

- `Inter`

Fallback:

- `ui-sans-serif`
- `system-ui`
- `-apple-system`
- `BlinkMacSystemFont`
- `"Segoe UI"`
- `sans-serif`

Motivo: Inter tem excelente legibilidade para dashboards, tabelas, formulários e interfaces densas.

### 5.2 Escala tipográfica

| Token | Tamanho | Line-height | Peso | Uso |
|---|---:|---:|---:|---|
| `text.display` | 32px | 40px | 700 | Títulos raros de páginas estratégicas |
| `text.h1` | 24px | 32px | 700 | Título principal de página |
| `text.h2` | 20px | 28px | 650 | Título de seção |
| `text.h3` | 18px | 26px | 650 | Título de painel |
| `text.body` | 14px | 22px | 400 | Texto padrão |
| `text.body-strong` | 14px | 22px | 600 | Ênfase em listas e tabelas |
| `text.small` | 13px | 20px | 400 | Texto de apoio |
| `text.caption` | 12px | 18px | 500 | Labels auxiliares, badges |
| `text.table` | 13px | 20px | 400 | Células de tabela |
| `text.label` | 13px | 18px | 600 | Labels de formulário |

### 5.3 Regras de texto

- Não usar fonte menor que 12px.
- Não usar letter-spacing negativo.
- Usar no máximo dois pesos por área: normal e semibold/bold.
- Títulos de página devem ser objetivos: "Clientes", "Documentos", "Agenda".
- Subtítulos devem explicar contexto, não repetir o título.
- Texto em tabelas deve truncar com tooltip quando necessário.
- Números importantes devem usar peso 600.

### 5.4 Formatação de dados

- Datas curtas: `26/04/2026`.
- Datas com horário: `26/04/2026 14:30`.
- Moeda: `R$ 1.250,00`.
- Percentual: `12,5%`.
- CPF/CNPJ: aplicar máscara visual, mas preservar dado normalizado no backend.
- Documento sensível mascarado: `*******8901`.
- Prazos: usar texto claro: `Vence hoje`, `Vence em 3 dias`, `Atrasado há 2 dias`.

## 6. Layout e Espaçamento

### 6.1 Grid e estrutura

A aplicação interna deve usar:

- Sidebar fixa em desktop.
- Topbar fixa ou sticky.
- Área principal com largura fluida.
- Conteúdo com `max-width` apenas quando a tela for formulário ou detalhe.
- Tabelas e dashboards devem usar toda a largura útil.

### 6.2 Dimensões recomendadas

| Elemento | Dimensão |
|---|---:|
| Sidebar expandida | 264px |
| Sidebar compacta | 72px |
| Topbar | 64px |
| Footer | 40px |
| Container de formulário | 960px |
| Container de leitura/detalhe | 1120px |
| Raio de borda padrão | 8px |
| Raio de borda pequeno | 6px |
| Raio de borda pill | 999px |

### 6.3 Escala de espaçamento

| Token | Valor |
|---|---:|
| `space.1` | 4px |
| `space.2` | 8px |
| `space.3` | 12px |
| `space.4` | 16px |
| `space.5` | 20px |
| `space.6` | 24px |
| `space.8` | 32px |
| `space.10` | 40px |
| `space.12` | 48px |

### 6.4 Densidade

Usar três densidades:

- Compacta: tabelas, listas operacionais, menus.
- Padrão: formulários, detalhes de cliente, cards de status.
- Confortável: páginas de autenticação e empty states.

Não usar espaçamento exagerado em dashboards operacionais.

## 7. Componentes Base

## 7.1 Botões

### Variantes

| Variante | Uso |
|---|---|
| Primário | Ação principal da tela: salvar, criar, confirmar |
| Secundário | Ação alternativa: editar, filtrar, visualizar |
| Ghost | Ações leves em toolbar e tabelas |
| Danger | Ações destrutivas: excluir, cancelar, recusar |
| Icon-only | Ações compactas com tooltip e aria-label |

### Tamanhos

| Tamanho | Altura | Padding | Fonte |
|---|---:|---:|---:|
| sm | 32px | 12px | 13px |
| md | 40px | 16px | 14px |
| lg | 44px | 18px | 14px |
| icon-sm | 32px | 0 | ícone 16px |
| icon-md | 40px | 0 | ícone 18px |

### Regras

- Todo botão deve ter estado hover, active, focus, disabled e loading.
- Botões icon-only devem ter tooltip e aria-label.
- Ação principal deve aparecer uma vez por contexto.
- Não usar botões longos com texto quebrando linha.
- Loading deve preservar largura do botão.

## 7.2 Inputs de texto

### Aparência

- Altura padrão: 40px.
- Borda: `neutral.300`.
- Background: `neutral.0`.
- Texto: `neutral.900`.
- Placeholder: `neutral.400`.
- Raio: 8px.
- Padding horizontal: 12px.

### Estados

- Focus: borda `brand.primary`, focus ring `#93C5FD`.
- Error: borda `#DC2626`, mensagem abaixo em vermelho.
- Disabled: background `neutral.100`, texto `neutral.400`.
- Readonly: background `neutral.50`, borda `neutral.200`.

### Regras

- Label sempre visível.
- Placeholder não substitui label.
- Texto auxiliar fica abaixo do campo.
- Erro fica abaixo do campo e substitui ou acompanha o texto auxiliar.

## 7.3 Textarea

- Altura mínima: 96px.
- Redimensionamento vertical permitido quando útil.
- Usar contador de caracteres apenas quando houver limite relevante.
- Ideal para observações, instruções, motivos de recusa e atas.

## 7.4 Select, combobox e autocomplete

- Select simples para listas pequenas e estáveis.
- Combobox para clientes, responsáveis, categorias e tags.
- Autocomplete deve mostrar estado vazio e loading.
- Sempre permitir limpar seleção quando o campo for opcional.

## 7.5 Date picker

- Usar formato visual `dd/mm/aaaa`.
- Permitir digitação e seleção.
- Mostrar atalhos quando fizer sentido: hoje, amanhã, próxima semana.
- Em prazos, destacar vencimentos próximos e passados.

## 7.6 Currency input

- Prefixo visual `R$`.
- Alinhamento à direita em tabelas.
- Alinhamento normal em formulários.
- Não aceitar valores negativos salvo campo explicitamente financeiro de ajuste.

## 7.7 Search input

- Ícone de busca à esquerda.
- Placeholder objetivo: `Buscar cliente`, `Buscar documento`.
- Botão de limpar quando preenchido.
- Debounce em buscas remotas.

## 7.8 Checkbox, radio e toggle

- Checkbox: múltipla seleção ou confirmação pontual.
- Radio: escolha única entre opções curtas.
- Toggle: configuração binária persistente.
- Label clicável.
- Área mínima clicável: 40px de altura.

## 7.9 Badges e status pills

### Badges

Usar para tags, categorias e metadados curtos.

- Altura: 24px.
- Fonte: 12px, peso 500.
- Raio: 999px.
- Padding: 8px horizontal.

### Status pills

Usar para estados de domínio.

Exemplos:

- `Ativo`
- `Inativo`
- `Pendente`
- `Em análise`
- `Aprovado`
- `Recusado`
- `Atrasado`
- `Concluído`
- `Cancelado`

Status pills devem usar cor semântica e texto explícito.

## 7.10 Cards

Usar cards apenas para:

- Métricas.
- Entidades repetidas em grid.
- Painéis de resumo.
- Blocos de detalhe.

Regras:

- Não colocar card dentro de card.
- Raio máximo: 8px.
- Sombra muito sutil ou nenhuma.
- Borda padrão `neutral.200`.
- Header do card com título curto e ação opcional.

## 7.11 Tabelas

Tabelas são componentes centrais do sistema.

### Estrutura

- Header fixo quando a tabela for longa.
- Fonte de célula: 13px.
- Altura de linha: 44px a 52px.
- Zebra striping opcional e muito sutil.
- Hover em linha: `neutral.50`.
- Ações alinhadas à direita.

### Colunas

Regras:

- Primeira coluna deve identificar a entidade.
- Colunas de status devem usar pill.
- Datas e prazos devem ter destaque quando vencidos.
- Valores monetários alinhados à direita.
- Ações devem ser compactas e consistentes.

### Estados

- Loading: skeleton de linhas.
- Empty: mensagem objetiva e ação primária, quando aplicável.
- Erro: alert inline com ação de tentar novamente.

## 7.12 Tabs

- Usar tabs para alternar visões de mesmo contexto.
- Não usar tabs como navegação principal.
- Estado ativo com borda inferior ou background sutil.
- Exemplo: ficha do cliente com `Resumo`, `Documentos`, `Tarefas`, `Financeiro`, `Histórico`.

## 7.13 Breadcrumbs

- Usar em páginas internas profundas.
- Exemplo: `Clientes / Maria Silva / Documentos`.
- Último item não é clicável.
- Em mobile, pode ocultar itens intermediários.

## 7.14 Dropdowns e menus contextuais

- Usar para ações secundárias.
- Ação destrutiva sempre separada visualmente.
- Fechar ao selecionar item.
- Suportar teclado.
- Largura mínima: 180px.

## 7.15 Modals

Usar para:

- Confirmações.
- Formulários curtos.
- Ações destrutivas.
- Mudanças críticas.

Regras:

- Título claro.
- Texto objetivo.
- Botões no rodapé.
- Botão principal à direita.
- Fechar com ESC quando seguro.
- Não usar modal para formulários longos.

## 7.16 Drawers

Usar para:

- Detalhes rápidos.
- Filtros avançados.
- Criação contextual sem sair da tela.

Regras:

- Largura desktop: 420px a 560px.
- Em mobile ocupa tela inteira.
- Deve ter título, conteúdo rolável e footer fixo quando houver ações.

## 7.17 Empty states

Empty states devem ser úteis, não decorativos.

Estrutura:

- Ícone simples.
- Título curto.
- Texto explicativo de uma frase.
- Ação principal quando houver próximo passo claro.

Exemplo:

- Título: `Nenhum documento solicitado`
- Texto: `Solicite documentos ao cliente para acompanhar pendências por item.`
- Ação: `Nova solicitação`

## 7.18 Loading e skeleton

- Usar skeleton em tabelas, cards e blocos de detalhe.
- Usar spinner apenas para ações curtas e botões.
- Nunca bloquear a tela inteira sem necessidade.
- Loading deve preservar layout para evitar saltos.

## 7.19 Tooltips

- Usar para botões icon-only, textos truncados e termos técnicos.
- Não colocar instruções essenciais apenas em tooltip.
- Delay curto: 300ms.

## 7.20 Toasts

### Tipos

- Sucesso.
- Erro.
- Aviso.
- Informação.

### Regras

- Posição: canto superior direito em desktop.
- Mobile: topo com margem segura.
- Duração padrão: 4s.
- Erros críticos devem permanecer até o usuário fechar.
- Texto curto e acionável.

Exemplos:

- `Cliente atualizado com sucesso.`
- `Não foi possível salvar. Revise os campos destacados.`
- `Documento enviado para análise.`

## 7.21 Alerts inline

Usar dentro do fluxo quando a mensagem afeta uma área específica.

Exemplos:

- Cliente sem responsável.
- Documento vencido.
- Prazo exige revisão antes da conclusão.
- Usuário sem permissão para editar.

## 7.22 Paginação

- Paginação padrão em tabelas.
- Exibir total quando útil.
- Permitir seleção de `15`, `30`, `50` itens por página.
- Em mobile, usar controles compactos.

## 7.23 Avatares

- Usar iniciais quando não houver foto.
- Tamanhos: 24px, 32px, 40px.
- Cores derivadas de forma estável por usuário.
- Sempre acompanhados de nome em listas importantes.

## 8. Navegação

## 8.1 Sidebar

### Estrutura sugerida

- Dashboard.
- Clientes.
- Documentos.
- Solicitações.
- Tarefas.
- Prazos.
- Agenda.
- Financeiro.
- Relatórios.
- Configurações.

### Aparência

- Background: `neutral.0`.
- Borda direita: `neutral.200`.
- Item ativo: background `#EFF6FF`, texto `#1D4ED8`.
- Item hover: `neutral.50`.
- Ícones com `lucide-vue-next`, 18px.
- Texto 14px, peso 500.

### Comportamento

- Desktop: expandida por padrão.
- Desktop compacto: somente ícones com tooltip.
- Mobile: drawer acionado por botão.
- Estado ativo deve refletir rota atual.

## 8.2 Topbar

Conteúdo:

- Título da página.
- Breadcrumb ou contexto.
- Busca global preparada para futuro.
- Seletor de organização ativa.
- Notificações/lembretes.
- Menu de usuário.

Regras:

- Altura: 64px.
- Background: `neutral.0`.
- Borda inferior: `neutral.200`.
- Ações alinhadas à direita.
- Em mobile, reduzir busca e priorizar menu.

## 8.3 Menu do usuário

Itens:

- Nome e e-mail.
- Perfil.
- Organização ativa.
- Configurações.
- Sair.

Logout deve ser visualmente separado, mas não usar vermelho agressivo salvo confirmação destrutiva.

## 8.4 Troca de organização

- Exibir nome da organização ativa.
- Permitir trocar organização em dropdown.
- Mostrar status da organização quando necessário.
- Evitar troca acidental em fluxos de edição.

## 9. Formulários

### 9.1 Layout

- Formulários curtos podem ficar em uma coluna.
- Formulários longos devem ser divididos em seções.
- Usar grid de 2 colunas em desktop quando os campos forem relacionados.
- Mobile sempre uma coluna.
- Ações principais no final e, em telas longas, também em footer sticky.

### 9.2 Campos obrigatórios

- Indicar obrigatoriedade com texto ou asterisco discreto.
- Não depender apenas de cor.
- Mensagens de erro devem informar o problema e, quando possível, a correção.

### 9.3 Validação

- Validar no backend como fonte de verdade.
- Exibir erro por campo.
- Exibir alerta geral apenas quando necessário.
- Preservar dados preenchidos após erro.
- Focar o primeiro campo com erro quando o envio falhar.

### 9.4 Ações

Padrão:

- Primária: `Salvar`, `Criar cliente`, `Enviar documento`.
- Secundária: `Cancelar`.
- Destrutiva: `Excluir`, `Cancelar solicitação`, `Recusar`.

Quando houver risco de perda de dados, pedir confirmação antes de sair.

## 10. Tabelas e Listagens

### 10.1 Filtros

Filtros comuns:

- Busca textual.
- Status.
- Responsável.
- Cliente.
- Categoria.
- Período.
- Prioridade.

Regras:

- Filtros principais ficam visíveis.
- Filtros avançados podem ir para drawer.
- Filtros aplicados devem aparecer como chips removíveis.
- Botão `Limpar filtros` deve aparecer quando houver filtros ativos.

### 10.2 Ações por linha

- Ação primária por clique na linha ou botão textual.
- Ações secundárias em menu contextual.
- Ações destrutivas dentro do menu, separadas.

### 10.3 Bulk actions

Preparar para uso futuro, mas não implementar em todas as tabelas no início.

Exemplos futuros:

- Arquivar documentos.
- Atribuir tarefas.
- Alterar status.
- Exportar.

## 11. Padrões por Domínio

### 11.1 Clientes

Elementos visuais:

- Nome em destaque.
- Tipo PF/PJ.
- Status.
- Prioridade.
- Risco.
- Responsável principal.
- Tags.

Clientes restritos devem ter indicador visual discreto de acesso restrito.

### 11.2 Documentos

Estados:

- Recebido.
- Em análise.
- Aprovado.
- Recusado.
- Vencido.
- Substituído.

Documentos sensíveis devem ter indicador de sensibilidade e ações de acesso devem ser auditáveis.

### 11.3 Solicitações documentais

Usar progresso por itens:

- Solicitado.
- Recebido.
- Aprovado.
- Recusado.
- Cancelado.

Recusas devem destacar o motivo e orientar novo envio.

### 11.4 Tarefas

Prioridades:

- Baixa: neutro.
- Normal: azul.
- Alta: âmbar.
- Crítica: vermelho.

Tarefas atrasadas devem ser destacadas por status derivado, não por estado manual.

### 11.5 Prazos

Prazos precisam de destaque visual maior que tarefas comuns.

Exibir:

- Data do prazo.
- Urgência.
- Responsável.
- Status de revisão.
- Atraso ou proximidade.

### 11.6 Agenda

Tipos:

- Reunião.
- Evento interno.
- Prazo.
- Audiência futura.

Usar cores leves por tipo, sem transformar a agenda em um mosaico colorido demais.

### 11.7 Financeiro

Estados:

- Em aberto.
- Pago.
- Atrasado.
- Parcial.
- Cancelado.

Valores monetários devem ter alinhamento e contraste adequados. Atrasos e inadimplência devem ser claros.

## 12. Microcopy

### 12.1 Tom de voz

- Claro.
- Objetivo.
- Respeitoso.
- Sem jargão técnico desnecessário.
- Sem mensagens excessivamente informais.

### 12.2 Padrões de texto

Preferir:

- `Salvar alterações`
- `Criar cliente`
- `Enviar documento`
- `Solicitar revisão`
- `Recusar documento`
- `Concluir tarefa`

Evitar:

- `Submeter`
- `Processar`
- `Executar ação`
- `Clique aqui`

### 12.3 Mensagens de erro

Boas mensagens:

- `Informe um e-mail válido.`
- `Selecione um responsável ativo.`
- `Este documento não pode ser concluído sem aprovação.`
- `Você não tem permissão para acessar este cliente.`

Evitar:

- `Erro inesperado.`
- `Falha.`
- `Campo inválido.`

Quando o erro for genérico:

- `Não foi possível concluir a ação. Tente novamente.`

## 13. Iconografia

### 13.1 Biblioteca

Usar `lucide-vue-next`.

### 13.2 Tamanhos

| Uso | Tamanho |
|---|---:|
| Botão pequeno | 16px |
| Botão médio | 18px |
| Navegação | 18px |
| Empty state | 32px |
| Métrica | 20px |

### 13.3 Ícones sugeridos

| Contexto | Ícone |
|---|---|
| Dashboard | `LayoutDashboard` |
| Clientes | `Users` |
| Documentos | `Files` |
| Solicitações | `FileQuestion` |
| Tarefas | `CheckSquare` |
| Prazos | `Clock` |
| Agenda | `CalendarDays` |
| Financeiro | `CircleDollarSign` |
| Relatórios | `ChartColumn` |
| Configurações | `Settings` |
| Busca | `Search` |
| Filtros | `SlidersHorizontal` |
| Download | `Download` |
| Upload | `Upload` |
| Mais ações | `MoreHorizontal` |
| Sair | `LogOut` |

## 14. Acessibilidade

### 14.1 Contraste

- Texto principal deve ter contraste mínimo 4.5:1.
- Texto grande deve ter contraste mínimo 3:1.
- Estados não podem depender apenas de cor.

### 14.2 Foco

- Todo elemento interativo deve ter foco visível.
- Focus ring deve ser consistente.
- Não remover outline sem substituto acessível.

### 14.3 Teclado

- Dropdowns, modals, drawers e menus devem funcionar com teclado.
- ESC fecha overlays quando não houver perda de dados.
- Tab deve seguir ordem visual.

### 14.4 Botões icon-only

Todo botão somente com ícone deve ter:

- `aria-label`.
- Tooltip.
- Área clicável mínima de 40px.

### 14.5 Formulários

- Labels associados aos campos.
- Erros associados via `aria-describedby`.
- Campos obrigatórios identificados.
- Mensagens de erro claras.

## 15. Responsividade

### 15.1 Breakpoints

| Nome | Largura |
|---|---:|
| Mobile | até 639px |
| Tablet | 640px a 1023px |
| Desktop | 1024px a 1439px |
| Wide | 1440px+ |

### 15.2 Mobile

- Sidebar vira drawer.
- Topbar reduz busca e ações secundárias.
- Tabelas viram lista responsiva ou scroll horizontal controlado.
- Formulários ficam em uma coluna.
- Ações principais devem ficar visíveis no final da tela ou em footer sticky.

### 15.3 Desktop

- Sidebar expandida.
- Tabelas densas.
- Filtros principais visíveis.
- Painéis lado a lado quando útil.

## 16. Tokens para Implementação

### 16.1 CSS variables sugeridas

```css
:root {
  --color-brand-primary: #2563EB;
  --color-brand-primary-hover: #1D4ED8;
  --color-brand-primary-active: #1E40AF;
  --color-brand-secondary: #0F766E;
  --color-brand-accent: #D97706;

  --color-bg-app: #F8FAFC;
  --color-bg-surface: #FFFFFF;
  --color-bg-muted: #F1F5F9;

  --color-border: #E2E8F0;
  --color-border-strong: #CBD5E1;

  --color-text-primary: #0F172A;
  --color-text-secondary: #475569;
  --color-text-muted: #64748B;
  --color-text-disabled: #94A3B8;

  --color-success-bg: #ECFDF5;
  --color-success-text: #047857;
  --color-warning-bg: #FFFBEB;
  --color-warning-text: #B45309;
  --color-error-bg: #FEF2F2;
  --color-error-text: #DC2626;
  --color-info-bg: #EFF6FF;
  --color-info-text: #2563EB;

  --radius-sm: 6px;
  --radius-md: 8px;
  --radius-pill: 999px;

  --shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.06);
  --shadow-md: 0 8px 24px rgba(15, 23, 42, 0.08);
}
```

### 16.2 Tailwind theme sugerido

Quando Tailwind for configurado, mapear:

- `primary`
- `secondary`
- `accent`
- `surface`
- `muted`
- `border`
- `success`
- `warning`
- `danger`
- `info`

Evitar usar classes de cor arbitrárias espalhadas na aplicação. Componentes devem consumir tokens.

## 17. Modo Escuro Futuro

O modo claro é o padrão inicial.

Para permitir modo escuro no futuro:

- Usar tokens semânticos desde o início.
- Evitar cores hardcoded em componentes.
- Separar cor de status de cor de superfície.
- Testar contraste dos estados antes de liberar.

Não implementar modo escuro nesta fase.

## 18. Critérios de Qualidade Visual

Antes de considerar uma tela pronta:

- O título da página é claro.
- A ação principal é evidente.
- A hierarquia visual permite escanear a tela em poucos segundos.
- Estados de loading, vazio e erro foram tratados.
- O formulário tem labels e erros claros.
- Tabelas têm filtros e ações previsíveis.
- A tela funciona em mobile e desktop.
- Não há texto sobreposto.
- Botões não mudam de tamanho ao entrar em loading.
- Cores seguem os tokens.
- Componentes têm foco visível.

## 19. Primeiros Componentes Recomendados

Implementar primeiro:

- `Button`
- `IconButton`
- `Input`
- `Textarea`
- `Select`
- `Checkbox`
- `Badge`
- `StatusPill`
- `Alert`
- `Toast`
- `DropdownMenu`
- `Modal`
- `Drawer`
- `Table`
- `Pagination`
- `EmptyState`
- `AppSidebar`
- `AppTopbar`
- `AuthLayout`
- `AppLayout`

Esses componentes cobrem a base das próximas telas e reduzem divergência visual.

## 20. Conclusão

A identidade visual do Docflow deve apoiar uma operação séria e recorrente. O design deve facilitar controle, reduzir ruído, evidenciar pendências e permitir que o usuário encontre rapidamente clientes, documentos, tarefas, prazos e informações financeiras.

O resultado esperado não é uma interface chamativa, mas uma ferramenta de trabalho confiável, agradável e eficiente.
