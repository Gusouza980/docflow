# Guia de Uso do Docflow

Este guia descreve o uso funcional do Docflow conforme implementado ate a Sprint 07: base SaaS, autenticacao, clientes, documentos, tarefas, prazos, agenda, financeiro basico, portal do cliente, comunicacao e relatorios.

O sistema possui duas superficies principais:

- Aplicacao web interna: usada pela equipe do escritorio em rotas como `/dashboard`, `/clients`, `/documents`, `/tasks`, `/finance`, `/portal` e `/reports`.
- Portal externo do cliente: usado pelo cliente final por link seguro em `/client-portal/{token}`.

Tambem existe uma API versionada em `/api/v1`, usada para integracoes e para expor as mesmas capacidades principais com contexto de organizacao ativa.

## Conceitos Base

### Organizacao

A organizacao representa o escritorio ou unidade de trabalho. Todos os dados operacionais pertencem a uma organizacao: clientes, documentos, tarefas, financeiro, portal e relatorios.

Na aplicacao web, a organizacao ativa fica na sessao do usuario. Na API, as rotas protegidas por contexto usam o cabecalho `X-Organization-Id`.

### Usuario Interno

Usuario interno e qualquer pessoa da equipe que acessa a aplicacao web autenticada. Um usuario pode pertencer a uma ou mais organizacoes, com papeis diferentes em cada uma.

### Cliente

Cliente e a pessoa fisica ou juridica atendida pelo escritorio. Ele pode ter contatos, responsaveis internos, etiquetas, documentos, tarefas, cobrancas, mensagens, chamados e relatorios mensais.

### Cliente Externo no Portal

O cliente externo nao e um usuario interno. Ele acessa o portal por um link seguro gerado pela equipe. Esse link e vinculado a um cliente especifico e nao permite acessar dados de outros clientes.

## Papeis e Permissoes

Os papeis implementados sao:

- Administrador (`admin`)
- Gestor (`manager`)
- Profissional (`professional`)
- Assistente (`assistant`)
- Financeiro (`finance`)
- Somente leitura (`readonly`)
- Cliente externo via portal

### Administrador

Pode gerenciar a organizacao, membros, convites, clientes, documentos, tarefas, prazos, agenda, portal, relatorios e financeiro. Tambem pode suspender e reativar membros, exceto quando isso removeria o ultimo administrador ativo.

### Gestor

Tem visao ampla da organizacao e pode operar a maioria dos modulos. Pode acessar clientes restritos de forma ampla, gerenciar atividades, documentos, portal e relatorios. Tambem pode cadastrar agendamentos de relatorios. O financeiro e permitido ao gestor.

### Profissional

Pode operar clientes aos quais tem acesso, incluindo clientes de acesso geral e clientes restritos sob sua responsabilidade ou liberados explicitamente. Pode criar e atualizar tarefas, documentos, solicitacoes, prazos e eventos, desde que respeite as regras de acesso ao cliente.

### Assistente

Pode apoiar operacoes em clientes acessiveis, documentos, solicitacoes, tarefas, prazos e agenda. Nao acessa o financeiro. O acesso a clientes restritos depende de responsabilidade ou liberacao explicita.

### Financeiro

Tem acesso ao modulo financeiro e aos relatorios financeiros. Tambem pode visualizar dados operacionais conforme as regras de acesso a clientes. E o perfil indicado para registrar contas a receber, pagamentos, despesas e acompanhar inadimplencia.

### Somente Leitura

Pode visualizar informacoes permitidas, mas nao pode criar, editar, concluir, cancelar ou excluir registros operacionais. Campos sensiveis podem ser omitidos em algumas telas, como potencial de receita na ficha do cliente.

### Cliente Externo

Acessa apenas o portal vinculado ao token recebido. Pode visualizar dados liberados para seu cliente, enviar mensagens e abrir solicitacoes/chamados. Nao acessa a aplicacao interna, outros clientes, dados internos, observacoes privadas ou configuracoes da organizacao.

## Navegacao Principal

A barra lateral interna organiza as paginas:

- Dashboard
- Organizacoes
- Equipe
- Clientes
- Documentos
- Solicitacoes
- Tarefas
- Modelos
- Prazos
- Agenda
- Financeiro
- Portal
- Relatorios

Cada pagina e uma tela Vue/Inertia renderizada por um controller web. As acoes de formulario normalmente usam POST/PATCH/DELETE web e retornam para a mesma tela com mensagens de sucesso ou erro.

## Fluxo de Acesso e Organizacao

### Login

O usuario acessa `/login`, informa e-mail e senha e e direcionado ao dashboard. A API tambem possui login em `POST /api/v1/auth/login`, retornando token Sanctum.

### Recuperacao de Senha

O usuario pode solicitar redefinicao em `/forgot-password` e concluir o processo em `/reset-password/{token}`.

### Organizacoes

Pagina: `/organizations`

Permite:

- Criar organizacao.
- Editar dados basicos da organizacao.
- Trocar a organizacao ativa.

A organizacao ativa define o escopo de todos os dados exibidos. Um usuario sem organizacao ativa e redirecionado para selecionar ou criar uma organizacao.

### Equipe e Convites

Pagina: `/team`

Permite:

- Visualizar membros da organizacao.
- Convidar novos membros.
- Cancelar convites pendentes.
- Suspender membros.
- Reativar membros.

Somente administradores gerenciam equipe e convites. O sistema impede suspender o ultimo administrador ativo.

Fluxo comum:

1. Administrador acessa Equipe.
2. Cria convite informando nome, e-mail e papel.
3. Pessoa convidada acessa `/invitations/{token}/accept`.
4. Ao aceitar, passa a integrar a organizacao.

## Dashboard

Pagina: `/dashboard`

O dashboard concentra indicadores e alertas iniciais da organizacao ativa.

Mostra:

- Clientes ativos, inativos, em negociacao, inadimplentes e encerrados.
- Clientes de alto risco.
- Clientes sem contato principal.
- Clientes sem responsavel.
- Tarefas abertas, atrasadas e concluidas.
- Documentos pendentes, vencidos e proximos do vencimento.
- Chamados abertos.
- Alertas criticos com base em tarefas e documentos vencidos.
- Pendencias estruturais de clientes.

Como se comunica:

- A pagina web usa `Web\DashboardController`.
- A API equivalente e `GET /api/v1/dashboard`.
- As consultas respeitam organizacao ativa e acesso a clientes restritos.

## Clientes

### Lista de Clientes

Pagina: `/clients`

Objetivo: gerenciar a base de clientes PF/PJ da organizacao.

Permite:

- Buscar clientes.
- Filtrar por status, responsavel, prioridade, risco e outros criterios implementados.
- Criar cliente.
- Editar cliente por modal.
- Acessar ficha do cliente.

Campos principais:

- Tipo: pessoa fisica ou juridica.
- Nome de exibicao.
- Documento CPF/CNPJ.
- Status.
- Prioridade.
- Risco.
- Potencial de receita.
- Origem.
- Politica de acesso.
- Responsavel principal.

Regras importantes:

- Documento deve ser unico dentro da organizacao.
- Cliente ativo deve ter pelo menos um responsavel interno.
- Usuarios sem acesso a cliente restrito nao visualizam nem alteram esse cliente.
- Somente leitura nao cria nem edita clientes.

### Ficha do Cliente

Pagina: `/clients/{client}`

Mostra:

- Dados cadastrais.
- Perfil PF ou PJ.
- Contatos.
- Responsaveis internos.
- Etiquetas.
- Status, prioridade e risco.
- Informacoes sensiveis conforme permissao.

Permite:

- Atualizar dados cadastrais.
- Alterar status.
- Criar contatos.
- Criar e associar etiquetas.
- Remover contatos.
- Consultar relacoes operacionais preparadas para documentos, tarefas, financeiro, mensagens e demais modulos.

### Contatos

Contatos pertencem ao cliente e podem ser usados para comunicacao operacional, financeira ou geral. E possivel marcar contato principal.

### Etiquetas

Etiquetas ajudam a classificar clientes. Gestores e administradores podem gerenciar etiquetas com mais liberdade; usuarios somente leitura apenas visualizam.

### Acesso Restrito a Clientes

Clientes podem ser de acesso geral ou restrito.

Clientes de acesso geral podem ser vistos por membros ativos da organizacao.

Clientes restritos podem ser vistos por:

- Administradores.
- Gestores.
- Responsaveis do cliente.
- Membros liberados explicitamente.

## Documentos

### Documentos

Pagina: `/documents`

Objetivo: armazenar e controlar documentos privados da organizacao e dos clientes.

Permite:

- Enviar documento.
- Definir cliente vinculado.
- Definir categoria.
- Definir titulo, validade, sensibilidade e visibilidade.
- Filtrar documentos.
- Acessar detalhe do documento.

### Detalhe do Documento

Pagina: `/documents/{document}`

Mostra:

- Metadados.
- Cliente vinculado.
- Categoria.
- Validade.
- Versao atual.
- Historico de versoes.

Permite:

- Editar metadados.
- Enviar nova versao.
- Visualizar arquivo por rota segura.
- Baixar arquivo por rota segura.

Como se comunica:

- Arquivos nao sao expostos diretamente por caminho publico.
- Visualizacao usa `/documents/{document}/view`.
- Download usa `/documents/{document}/download`.
- A API possui equivalentes em `/api/v1/documents/{document}/view` e `/api/v1/documents/{document}/download`.

### Categorias Documentais

Categorias organizam documentos e podem ter validade padrao e sensibilidade.

Permite:

- Criar categoria.
- Editar categoria.
- Excluir categoria quando nao estiver em uso.

## Solicitacoes Documentais

### Lista de Solicitacoes

Pagina: `/document-requests`

Objetivo: controlar pedidos de documentos feitos ao cliente.

Permite:

- Criar solicitacao com multiplos itens.
- Informar cliente, titulo, instrucoes e prazo.
- Filtrar por cliente, status e atraso.
- Acessar detalhe.

### Detalhe da Solicitacao

Pagina: `/document-requests/{documentRequest}`

Mostra:

- Dados da solicitacao.
- Lista de itens solicitados.
- Status de cada item.
- Documentos enviados para cada item.

Permite:

- Enviar arquivo para item.
- Aprovar documento recebido.
- Recusar documento recebido com motivo.
- Cancelar solicitacao.

Fluxo comum:

1. Usuario interno cria solicitacao para um cliente.
2. Cada item nasce como solicitado.
3. Arquivo e enviado pelo time ou pelo portal quando disponivel.
4. Item passa para recebido/em analise.
5. Responsavel aprova ou recusa.
6. Recusa exige motivo e reabre pendencia.
7. Quando aplicavel, solicitacao pode ser cancelada.

## Tarefas

### Lista de Tarefas

Pagina: `/tasks`

Objetivo: organizar o trabalho operacional da equipe.

Permite:

- Criar tarefa.
- Filtrar por status, cliente, responsavel, prioridade e prazo.
- Visualizar status e progresso de checklist.
- Acessar detalhe.

Dados principais:

- Cliente.
- Responsavel.
- Titulo.
- Descricao.
- Status.
- Prioridade.
- Prazo.

Status principais:

- Pendente.
- Em andamento.
- Bloqueada.
- Concluida.
- Cancelada.

### Detalhe da Tarefa

Pagina: `/tasks/{task}`

Permite:

- Editar tarefa em modal.
- Alterar status.
- Concluir tarefa em modal.
- Adicionar item de checklist em modal.
- Marcar checklist como concluido.
- Remover item de checklist.

Regra importante:

- Checklist obrigatorio bloqueia conclusao enquanto nao estiver concluido.

### Modelos de Tarefas

Pagina: `/task-templates`

Objetivo: criar rotinas reutilizaveis.

Permite:

- Criar modelo.
- Editar modelo e seus itens.
- Definir tarefas internas do modelo.
- Definir prazos relativos.
- Definir checklists dentro dos itens.
- Criar tarefas reais a partir do modelo.

Fluxo comum:

1. Usuario cria modelo, por exemplo "Onboarding de cliente".
2. Adiciona itens com prazos relativos.
3. Seleciona cliente, responsavel e data base.
4. Sistema gera tarefas com prazos calculados.

## Prazos

Pagina: `/deadlines`

Objetivo: controlar prazos importantes separados de tarefas.

Permite:

- Criar prazo.
- Filtrar prazos.
- Editar prazo.
- Solicitar revisao.
- Aprovar revisao.
- Concluir prazo.

Campos principais:

- Cliente.
- Responsavel.
- Titulo.
- Tipo.
- Prazo.
- Urgencia/prioridade.
- Indicacao de revisao obrigatoria.

Regra importante:

- Quando o prazo exige revisao, ele precisa passar por solicitacao e aprovacao de revisao antes da conclusao.

## Agenda

Pagina: `/calendar`

Objetivo: registrar eventos, reunioes, audiencias e compromissos internos.

Permite:

- Criar evento.
- Editar evento.
- Informar cliente.
- Informar tipo.
- Definir inicio, fim e local.
- Adicionar participantes internos e externos.
- Registrar notas/ata.
- Criar tarefas a partir das notas de reuniao.

Fluxo comum:

1. Usuario cria evento de reuniao.
2. Adiciona participantes internos e externos.
3. Depois da reuniao, registra notas.
4. Opcionalmente cria tarefas derivadas da reuniao.

## Financeiro

Pagina: `/finance`

Objetivo: controlar financeiro basico do escritorio.

Perfis com acesso:

- Administrador.
- Gestor.
- Financeiro.

Perfis sem acesso:

- Assistente.
- Profissional sem permissao financeira dedicada.
- Somente leitura, conforme bloqueios do modulo.

### Indicadores Financeiros

A pagina mostra:

- Valor a receber.
- Inadimplencia.
- Valor a pagar.
- Saldo realizado.

### Contas a Receber

Permite:

- Criar conta a receber.
- Informar cliente, categoria, descricao, valor, vencimento, competencia e observacoes.
- Registrar pagamento total.
- Registrar pagamento parcial.
- Cancelar cobranca com motivo.

Status:

- Aberta.
- Parcial.
- Paga.
- Cancelada.

Regras:

- Pagamento nao pode exceder saldo.
- Pagamento parcial mantem cobranca em aberto/parcial.
- Pagamento total marca como paga.
- Cobranca paga nao pode ser cancelada.

### Contas a Pagar

Permite:

- Criar despesa.
- Informar fornecedor.
- Vincular a cliente quando aplicavel.
- Marcar como reembolsavel.
- Registrar pagamento.

### Categorias Financeiras

Permite criar categorias de:

- Receita.
- Despesa.
- Ambas.

As categorias alimentam filtros, classificacao e relatorios.

## Portal e Comunicacao Interna

Pagina interna: `/portal`

Objetivo: centralizar acessos externos do cliente, mensagens e chamados.

### Acessos do Portal

Permite:

- Gerar acesso para cliente.
- Informar nome e e-mail do contato.
- Definir data de expiracao.
- Copiar/usar link gerado.
- Revogar acesso.

O token e armazenado em hash. O link exibido contem apenas o token temporario em texto claro no momento de criacao.

### Mensagens

Permite:

- Registrar/enviar mensagem individual para cliente.
- Selecionar canal: e-mail, WhatsApp, telefone ou portal.
- Usar modelo de mensagem quando existir.
- Criar chamado a partir da mensagem.

Regra importante:

- Envio externo exige consentimento ativo para o canal/finalidade. Sem consentimento, o sistema bloqueia e informa erro.

### Modelos de Mensagem

Pela API ja existem endpoints para criar, editar, listar e remover modelos de mensagem. Os modelos podem usar variaveis como `{{client_name}}`.

### Consentimentos

Consentimentos registram autorizacao de comunicacao por:

- Cliente.
- Canal.
- Finalidade.

Um consentimento pode ser concedido ou revogado. Consentimento revogado bloqueia novos envios dependentes dele.

### Chamados

Chamados podem ser criados internamente ou pelo portal do cliente.

Status:

- Novo.
- Em analise.
- Aguardando cliente.
- Aguardando terceiro.
- Em execucao.
- Resolvido.
- Encerrado.

Chamados possuem:

- Cliente.
- Titulo.
- Descricao.
- Responsavel.
- Prioridade.
- Prazo.
- Visibilidade para cliente.
- Historico de mensagens.

## Portal do Cliente

Pagina externa: `/client-portal/{token}`

Objetivo: permitir que o cliente acompanhe dados liberados sem acessar a area interna.

O cliente pode visualizar:

- Nome do cliente.
- Solicitacoes documentais.
- Cobrancas.
- Chamados.
- Comunicados.
- Relatorios mensais liberados.

O cliente pode executar:

- Enviar mensagem para a equipe.
- Abrir solicitacao/chamado.

Regras de seguranca:

- O token precisa estar ativo e nao expirado.
- O portal mostra somente dados do cliente vinculado ao token.
- Dados internos, observacoes privadas e clientes de terceiros nao sao expostos.
- Acessos podem ser revogados pela equipe.

Como se comunica:

- A pagina web externa usa `Web\ClientPortalController`.
- A API externa do portal usa endpoints como:
  - `GET /api/v1/portal/me`
  - `GET /api/v1/portal/dashboard`
  - `GET /api/v1/portal/document-requests`
  - `GET /api/v1/portal/receivables`
  - `GET /api/v1/portal/tickets`
  - `POST /api/v1/portal/tickets`
  - `GET /api/v1/portal/announcements`
- A API externa do portal usa bearer token ou `X-Portal-Token`.

## Relatorios e Indicadores

Pagina: `/reports`

Objetivo: consolidar indicadores operacionais, documentais, financeiros e relatorios para cliente.

Permite:

- Filtrar por periodo e cliente.
- Ver visao geral.
- Ver produtividade por colaborador.
- Ver documentos pendentes e vencidos.
- Ver indicadores financeiros quando o perfil permite.
- Salvar filtros.
- Cadastrar agendamentos planejados.
- Gerar relatorio mensal do cliente.
- Liberar relatorio mensal para o portal.

### Visao Geral

Mostra:

- Clientes ativos.
- Clientes de alto risco.
- Clientes inadimplentes.
- Clientes sem contato principal.
- Tarefas abertas, atrasadas e concluidas.
- Documentos pendentes, vencidos e proximos do vencimento.
- Mensagens.
- Chamados abertos.
- Alertas criticos.

### Produtividade

Mostra por colaborador:

- Tarefas abertas.
- Tarefas concluidas no periodo.
- Tarefas atrasadas.
- Chamados abertos.

### Documentos

Mostra:

- Pendencias documentais.
- Documentos vencidos.
- Documentos proximos do vencimento.
- Itens com cliente, categoria, prazo e status.

### Financeiro

Disponivel apenas para administrador, gestor e financeiro.

Mostra:

- Contas a receber em aberto.
- Inadimplencia.
- Valores recebidos no periodo.
- Contas a pagar.
- Despesas pagas.
- Clientes inadimplentes.

### Filtros Salvos

Filtros salvos permitem reutilizar criterios de relatorio. Podem ser pessoais ou compartilhados com a equipe.

### Agendamentos

Agendamentos registram intencao futura de envio/geracao de relatorio. Nesta fase eles sao cadastro planejado, sem envio automatico real.

### Relatorio Mensal do Cliente

O relatorio mensal consolida:

- Tarefas concluidas e abertas.
- Solicitacoes documentais.
- Itens documentais pendentes.
- Chamados abertos e criados.
- Financeiro visivel.

Fluxo:

1. Usuario interno acessa Relatorios.
2. Clica em Relatorio mensal.
3. Seleciona cliente e periodo.
4. Sistema gera relatorio em estado revisado.
5. Usuario libera o relatorio.
6. Cliente passa a ver o relatorio no portal.

### Exportacao

A API possui exportacao CSV em `POST /api/v1/reports/export`. A exportacao gera auditoria com a acao `report.exported`.

## Comunicacao Entre Paginas e Modulos

### Clientes como eixo central

Clientes conectam quase todos os modulos:

- Documentos pertencem a clientes.
- Solicitacoes documentais pertencem a clientes.
- Tarefas podem pertencer a clientes.
- Prazos podem pertencer a clientes.
- Eventos podem pertencer a clientes.
- Cobrancas e despesas podem pertencer a clientes.
- Mensagens e chamados pertencem a clientes.
- Relatorios mensais pertencem a clientes.
- Portal externo e vinculado a clientes.

### Documentos e Solicitacoes

Solicitacoes documentais criam itens. Quando um arquivo e enviado para um item, um documento e vinculado ao item e pode ser aprovado ou recusado.

### Agenda e Tarefas

Uma reuniao pode gerar notas e, a partir delas, tarefas derivadas para membros da equipe.

### Mensagens e Chamados

Uma mensagem enviada ou recebida pode originar chamado. O chamado passa a ter seu proprio historico de mensagens.

### Financeiro e Portal

Cobrancas registradas no financeiro podem ser consultadas pelo cliente no portal, desde que estejam vinculadas ao cliente do token.

### Relatorios e Portal

Relatorios mensais gerados internamente ficam materializados. Depois de liberados, aparecem no portal do cliente.

## API Interna

A API interna usa o prefixo `/api/v1`.

Fluxo padrao:

1. Usuario autentica em `POST /api/v1/auth/login`.
2. Usa o token retornado como bearer token.
3. Informa `X-Organization-Id` nas rotas dependentes de organizacao.
4. Opera recursos conforme permissao.

Principais grupos:

- Autenticacao: `/auth/*`
- Organizacoes: `/organizations`
- Equipe: `/organization-members`, `/organization-invitations`
- Clientes: `/clients`
- Documentos: `/documents`, `/document-categories`, `/document-requests`
- Tarefas: `/tasks`, `/task-templates`
- Prazos: `/deadlines`
- Agenda: `/calendar-events`
- Comunicacao: `/message-templates`, `/messages`, `/communication-consents`, `/tickets`
- Relatorios: `/reports/*`, `/report-filters`, `/report-schedules`

## API do Portal

A API do portal nao usa usuario interno. Ela usa token de portal.

Token pode ser enviado por:

- Bearer token.
- Header `X-Portal-Token`.

Endpoints disponiveis:

- `GET /api/v1/portal/me`
- `GET /api/v1/portal/dashboard`
- `GET /api/v1/portal/document-requests`
- `GET /api/v1/portal/receivables`
- `GET /api/v1/portal/tickets`
- `POST /api/v1/portal/tickets`
- `GET /api/v1/portal/announcements`

## Auditoria

A auditoria registra acoes sensiveis na tabela `audit_logs`.

Exemplos de eventos auditados:

- Criacao e alteracao de organizacao.
- Convites e alteracoes de membros.
- Criacao/alteracao de clientes.
- Operacoes documentais sensiveis.
- Operacoes financeiras.
- Exportacao de relatorios.

Cada registro pode guardar:

- Organizacao.
- Usuario.
- Recurso auditado.
- Acao.
- Metadados.
- IP.
- User agent.

## Fluxos Operacionais Recomendados

### Onboarding de novo escritorio

1. Criar organizacao em `/organizations`.
2. Convidar equipe em `/team`.
3. Definir papeis corretos.
4. Criar clientes em `/clients`.
5. Cadastrar contatos principais.
6. Criar categorias documentais e financeiras conforme necessidade.

### Onboarding de novo cliente

1. Criar cliente PF/PJ.
2. Informar responsavel principal.
3. Adicionar contatos.
4. Aplicar etiquetas.
5. Criar solicitacao documental.
6. Criar tarefas iniciais manualmente ou via modelo.
7. Criar acesso ao portal se o cliente for acompanhar pendencias.

### Solicitacao documental

1. Acessar `/document-requests`.
2. Criar solicitacao para cliente.
3. Adicionar itens e prazos.
4. Receber upload.
5. Aprovar ou recusar.
6. Acompanhar pendencias por dashboard e relatorios.

### Operacao diaria da equipe

1. Abrir Dashboard para alertas.
2. Consultar tarefas em `/tasks`.
3. Ver prazos em `/deadlines`.
4. Ver agenda em `/calendar`.
5. Atualizar status e concluir atividades.
6. Registrar reunioes e criar tarefas derivadas.

### Controle financeiro basico

1. Acessar `/finance` com perfil permitido.
2. Criar categorias financeiras.
3. Criar contas a receber.
4. Registrar pagamentos parciais ou totais.
5. Criar contas a pagar.
6. Acompanhar inadimplencia.
7. Consultar relatorios financeiros em `/reports`.

### Comunicacao com cliente

1. Registrar consentimento pela API quando necessario.
2. Criar acesso ao portal em `/portal`.
3. Enviar mensagem ao cliente.
4. Se necessario, criar chamado a partir da mensagem.
5. Cliente responde ou abre solicitacao pelo portal.
6. Equipe acompanha chamados internamente.

### Relatorio mensal para cliente

1. Acessar `/reports`.
2. Gerar relatorio mensal para um cliente.
3. Revisar dados.
4. Liberar relatorio.
5. Cliente visualiza no portal.

## Limitacoes Atuais

Alguns pontos estao preparados estruturalmente, mas nao possuem integracao externa real nesta fase:

- Envio real por WhatsApp, e-mail transacional ou provedor externo.
- Gateway de pagamento, boleto, Pix, cartao ou webhook financeiro.
- Envio automatico real de relatorios agendados.
- Integracao com Google Calendar ou Outlook.
- BI avancado ou construtor visual de relatorios.
- App mobile nativo.

## Referencia Rapida por Pagina

| Pagina | Rota | Uso principal |
| --- | --- | --- |
| Login | `/login` | Entrar no sistema |
| Recuperar senha | `/forgot-password` | Solicitar redefinicao |
| Dashboard | `/dashboard` | Indicadores e alertas |
| Organizacoes | `/organizations` | Criar, editar e trocar organizacao |
| Equipe | `/team` | Membros e convites |
| Clientes | `/clients` | Listar e cadastrar clientes |
| Ficha do cliente | `/clients/{client}` | Dados completos do cliente |
| Documentos | `/documents` | Repositorio documental |
| Documento | `/documents/{document}` | Metadados e versoes |
| Solicitacoes | `/document-requests` | Pedidos documentais |
| Solicitacao | `/document-requests/{documentRequest}` | Itens, upload, aprovacao e recusa |
| Tarefas | `/tasks` | Trabalho operacional |
| Tarefa | `/tasks/{task}` | Detalhe, checklist e conclusao |
| Modelos | `/task-templates` | Modelos reutilizaveis de tarefas |
| Prazos | `/deadlines` | Prazos importantes e revisoes |
| Agenda | `/calendar` | Eventos e reunioes |
| Financeiro | `/finance` | Contas a receber, pagar e categorias |
| Portal interno | `/portal` | Acessos externos, mensagens e chamados |
| Portal do cliente | `/client-portal/{token}` | Area externa do cliente |
| Relatorios | `/reports` | Indicadores, filtros, agendamentos e relatorios mensais |

