# Documento Técnico — Plataforma de Gestão para Escritórios

## 1. Objetivo do documento

Este documento transforma o briefing de produto em uma referência técnica para desenvolvimento de uma plataforma de gestão para escritórios de advocacia, contabilidade, consultorias e serviços profissionais.

O sistema será desenvolvido inicialmente como uma API em Laravel, consumida por um aplicativo mobile e por uma aplicação web. A arquitetura deve favorecer consistência, segurança, rastreabilidade, evolução modular e operação SaaS.

## 2. Premissas principais

- O produto será uma plataforma SaaS multiempresa.
- A primeira entrega será uma API HTTP versionada.
- Os clientes da API serão, no mínimo, web app administrativo e aplicativo mobile.
- A ficha do cliente será o eixo central do domínio.
- Documentos, tarefas, prazos, comunicações e financeiro são módulos centrais, não complementares.
- Dados tratados pelo sistema são sensíveis e devem ser protegidos desde a modelagem inicial.
- O sistema deve atender escritórios pequenos sem impedir evolução para escritórios médios com equipe, permissões avançadas e automações.
- Automações e inteligência artificial devem apoiar a operação, nunca substituir decisão profissional jurídica, contábil ou consultiva.

## 3. Arquitetura recomendada

### 3.1 Estilo arquitetural

Recomendação inicial: monólito modular em Laravel.

Motivos:

- O domínio é amplo, mas ainda precisa de validação de produto.
- Laravel oferece boa produtividade para API, filas, eventos, jobs, notificações, políticas, testes e integrações.
- Um monólito modular reduz complexidade operacional no começo.
- Separar em microsserviços cedo aumentaria custo de deploy, observabilidade, consistência transacional e suporte.

O sistema deve ser organizado por módulos de negócio dentro da aplicação, com fronteiras claras entre responsabilidades. A separação deve acontecer primeiro por código, contratos internos e banco bem modelado; serviços externos podem surgir depois por necessidade real de escala ou isolamento.

### 3.2 Módulos internos sugeridos

- `Tenancy`: organizações, planos, usuários, membros e escopo de dados.
- `IdentityAccess`: autenticação, autorização, perfis, permissões e sessões.
- `Clients`: clientes PF/PJ, contatos, responsáveis, tags, risco e prioridade.
- `ServicesContracts`: serviços contratados, contratos, escopo, recorrência e reajustes.
- `Documents`: categorias, arquivos, versões, validade, solicitações e aprovações.
- `TasksDeadlines`: tarefas, checklists, prazos, agenda e eventos.
- `Communications`: mensagens, modelos, notificações, histórico e consentimentos.
- `Finance`: cobranças, receitas, despesas, pagamentos, parcelas e inadimplência.
- `CRM`: leads, propostas, funil, follow-ups e conversão.
- `ClientPortal`: acesso externo do cliente, envio de documentos e solicitações.
- `Reports`: indicadores, relatórios internos e relatórios para clientes.
- `Automation`: gatilhos, ações automáticas, jobs recorrentes e templates.
- `Audit`: trilhas de auditoria, visualizações, alterações e eventos sensíveis.
- `DomainVerticals`: extensões para advocacia, contabilidade e consultorias.

### 3.3 Camadas de código

Usar a estrutura padrão do Laravel, com extrações graduais quando o módulo crescer:

- Controllers finos, responsáveis por entrada HTTP, autorização e resposta.
- Form Requests para validação.
- API Resources para serialização.
- Actions para casos de uso de escrita e operações de negócio.
- Policies para autorização.
- Models Eloquent para estado, relações, casts e escopos simples.
- Query builders ou filtros dedicados para listagens complexas.
- Jobs para trabalho assíncrono.
- Events para fatos de domínio relevantes.
- Notifications e Mailables para comunicação.

Evitar controllers com regra de negócio extensa. Um método de controller deve ser curto e delegar o caso de uso para uma Action ou serviço específico.

## 4. Stack tecnológica

### 4.1 Backend principal

Tecnologia recomendada:

- PHP 8.3 ou superior.
- Laravel 13, conforme `composer.json` atual.
- PHPUnit para testes.
- Laravel Pint para padronização de estilo.
- Laravel Sail para ambiente local.
- Laravel Queue para processamento assíncrono.
- Laravel Scheduler para rotinas recorrentes.
- Laravel Notifications para e-mail, banco e canais futuros.

Pacotes recomendados para avaliar no início:

- Laravel Sanctum para autenticação de API first-party, SPA e mobile.
- Spatie Laravel Permission para permissões por papel, caso a matriz de acesso fique dinâmica.
- Spatie Laravel Activitylog ou implementação própria de auditoria, conforme granularidade exigida.
- Spatie Laravel Media Library apenas se o fluxo de documentos e versões encaixar bem; caso contrário, implementar módulo documental próprio.
- Laravel Horizon em produção quando Redis e filas crescerem em criticidade.

### 4.2 Banco de dados

Banco principal recomendado:

- PostgreSQL.

Motivos:

- Boa integridade relacional.
- Excelente suporte a índices, constraints, JSONB, full-text search e consultas analíticas moderadas.
- Adequado para SaaS multi-tenant com dados sensíveis.
- Mais robusto que SQLite/MySQL para relatórios, filtros e evolução de domínio.

Alternativas:

- MySQL 8: aceitável se a equipe já tiver forte domínio operacional, mas PostgreSQL continua preferível para este domínio.
- SQLite: somente para desenvolvimento local e testes simples, não para produção.

### 4.3 Cache, filas e tempo real

Recomendação:

- Redis para cache, locks, rate limits, filas e broadcasting.
- Laravel Horizon para monitorar filas em produção.
- Laravel Reverb ou Pusher para eventos em tempo real quando o produto exigir atualização instantânea de mensagens, tarefas e notificações.

Alternativas:

- SQS para filas se a infraestrutura estiver na AWS e houver necessidade de filas gerenciadas.
- RabbitMQ apenas se surgir necessidade específica de roteamento avançado de mensagens.

### 4.4 Armazenamento de documentos

Recomendação:

- S3 ou serviço compatível com S3 em produção.
- Disco local apenas em desenvolvimento.
- Arquivos privados por padrão.
- Acesso por URLs assinadas e temporárias.
- Metadados e permissões sempre no banco relacional.

Alternativas:

- Google Cloud Storage ou Azure Blob Storage, conforme infraestrutura do cliente.
- MinIO para ambiente privado ou homologação.

### 4.5 Busca

Fase inicial:

- Busca por banco com índices adequados e filtros bem modelados.
- PostgreSQL full-text search para busca textual básica em clientes, documentos, tarefas e mensagens.

Evolução:

- Meilisearch para busca simples, rápida e com boa experiência de desenvolvimento.
- OpenSearch/Elasticsearch se houver alto volume, auditoria de logs pesquisáveis ou consultas textuais avançadas.

### 4.6 Frontend web

Opções recomendadas:

- React com TypeScript, consumindo API REST.
- Vue com TypeScript, se a equipe preferir ecossistema Laravel/Vite.

Para painel administrativo denso, priorizar uma UI operacional, responsiva e orientada a produtividade, não uma landing page. A aplicação web deve ter navegação clara, tabelas fortes, filtros salvos, atalhos operacionais e visão de pendências.

Alternativas:

- Inertia.js com Laravel se a equipe quiser maior acoplamento entre backend e web. Não é a primeira recomendação porque o briefing exige API consumida também por mobile.
- Next.js/Nuxt apenas se houver necessidade forte de SSR público, o que não parece central para o produto.

### 4.7 Mobile

Opções recomendadas:

- React Native com TypeScript.
- Flutter, se a equipe já tiver domínio e quiser UI altamente consistente.

O app mobile deve priorizar:

- Consulta rápida da ficha do cliente.
- Envio e captura de documentos.
- Notificações.
- Tarefas, prazos e agenda.
- Portal do cliente, se houver app voltado ao cliente final.

### 4.8 Integrações externas

Integrações prováveis:

- WhatsApp Business Platform via provedor oficial.
- E-mail transacional via SES, Postmark, Mailgun ou Resend.
- Pagamentos via Asaas, Iugu, Pagar.me, Mercado Pago ou Stripe, conforme mercado e boleto/Pix.
- Assinatura digital via Clicksign, D4Sign, DocuSign ou equivalente.
- Calendário via Google Calendar e Microsoft Outlook em etapa futura.
- Inteligência artificial via API externa em módulo separado, com revisão humana e políticas de privacidade.

Toda integração externa deve ser encapsulada por interfaces próprias da aplicação, evitando dependência direta espalhada pelo domínio.

## 5. Estratégia de API

### 5.1 Padrão de API

Recomendação:

- REST JSON versionada, iniciando em `/api/v1`.
- Autenticação por bearer token.
- Respostas padronizadas por API Resources.
- Erros padronizados em JSON.
- Paginação obrigatória em listagens.
- Filtros e ordenação explícitos.
- Idempotência em endpoints críticos de cobrança, upload, webhooks e automações.

GraphQL não é recomendado para a primeira versão. Pode ser avaliado no futuro se os clientes web/mobile tiverem necessidades muito diferentes de composição de dados.

### 5.2 Convenções de endpoints

Exemplos:

- `GET /api/v1/clients`
- `POST /api/v1/clients`
- `GET /api/v1/clients/{client}`
- `GET /api/v1/clients/{client}/timeline`
- `GET /api/v1/clients/{client}/documents`
- `POST /api/v1/document-requests`
- `POST /api/v1/tasks`
- `PATCH /api/v1/tasks/{task}/status`
- `GET /api/v1/finance/receivables`
- `POST /api/v1/webhooks/payment-provider`

Listagens devem aceitar:

- `page`
- `per_page`
- `sort`
- `filter[...]`
- `include`, somente quando necessário e controlado

### 5.3 Versionamento

- Começar com `/api/v1`.
- Mudanças compatíveis entram na mesma versão.
- Mudanças incompatíveis devem abrir nova versão.
- Não remover campos sem janela de depreciação.
- Documentar mudanças em changelog técnico.

### 5.4 Contratos de resposta

Adotar envelopes consistentes:

```json
{
  "data": {},
  "meta": {},
  "links": {}
}
```

Para erros:

```json
{
  "message": "Não foi possível processar a solicitação.",
  "errors": {
    "field": ["Mensagem de validação."]
  }
}
```

Não vazar stack traces, IDs internos sensíveis, nomes de buckets ou detalhes de provedores externos em produção.

## 6. Multi-tenancy

### 6.1 Modelo recomendado

Recomendação inicial: banco compartilhado com coluna `organization_id` nas tabelas de negócio.

Motivos:

- Simples de operar.
- Bom para MVP e crescimento inicial.
- Facilita relatórios internos, suporte e manutenção.
- Evita custo de múltiplos bancos antes de haver necessidade real.

Cuidados obrigatórios:

- Todas as entidades de negócio devem pertencer a uma organização.
- Consultas devem sempre ser escopadas pela organização atual.
- Policies devem validar acesso ao recurso e à organização.
- Jobs devem receber e restaurar contexto de organização.
- Auditoria deve registrar organização.

Alternativas futuras:

- Banco por tenant para clientes enterprise, exigências contratuais fortes ou isolamento regulatório.
- Schema por tenant em PostgreSQL, se houver necessidade intermediária, embora aumente complexidade de migrations.

### 6.2 Entidades de tenancy

Entidades principais:

- `organizations`
- `organization_members`
- `users`
- `roles`
- `permissions`
- `plans`
- `subscriptions`

Um usuário pode participar de mais de uma organização. A sessão/token deve indicar a organização ativa ou permitir troca explícita.

## 7. Autenticação e autorização

### 7.1 Autenticação

Recomendação:

- Laravel Sanctum para tokens pessoais e autenticação first-party.
- Tokens separados por dispositivo.
- Revogação de sessão/dispositivo.
- MFA planejado para perfis administrativos e financeiros.
- Rate limit em login, recuperação de senha e endpoints sensíveis.

Fluxos mínimos:

- Cadastro de organização inicial.
- Convite de usuário.
- Aceite de convite.
- Login.
- Logout.
- Recuperação de senha.
- Troca de organização ativa.
- Gestão de dispositivos/tokens.

### 7.2 Autorização

Usar Policies do Laravel como base. Papéis iniciais:

- Administrador.
- Gestor.
- Financeiro.
- Profissional responsável.
- Assistente.
- Cliente.
- Somente leitura.

Permissões devem combinar:

- Papel do usuário.
- Organização ativa.
- Relação com o cliente/recurso.
- Sensibilidade do dado.
- Configuração de acesso do recurso.

Exemplos:

- Assistente pode ver documentos operacionais, mas não documentos financeiros sensíveis.
- Financeiro pode ver cobranças e pagamentos, mas não necessariamente casos sigilosos.
- Cliente só acessa recursos explicitamente vinculados a ele.
- Casos sigilosos exigem permissão explícita, além do papel.

## 8. Segurança, LGPD e sigilo

### 8.1 Princípios obrigatórios

- Privacidade por padrão.
- Menor privilégio.
- Coleta mínima de dados.
- Criptografia em trânsito.
- Armazenamento privado de documentos.
- Auditoria de ações sensíveis.
- Controle de retenção.
- Consentimento quando aplicável.
- Separação clara entre dados internos do escritório e dados visíveis ao cliente.

### 8.2 Dados sensíveis

Tratar como sensíveis:

- CPF, RG, CNPJ e documentos pessoais.
- Dados bancários.
- Documentos fiscais e trabalhistas.
- Contratos, procurações e certidões.
- Processos, casos jurídicos e estratégias.
- Folha de pagamento.
- Mensagens privadas.
- Informações financeiras do escritório e dos clientes.

Campos altamente sensíveis podem usar casts criptografados do Laravel. Antes de criptografar colunas que precisam de busca ou filtro, avaliar impacto técnico, pois criptografia dificulta pesquisa direta.

### 8.3 Auditoria

Registrar pelo menos:

- Criação, alteração e exclusão lógica de clientes.
- Upload, download, visualização, aprovação e recusa de documentos.
- Envio de mensagens.
- Alteração de prazo.
- Conclusão e reabertura de tarefas.
- Criação, baixa, cancelamento e renegociação de cobranças.
- Alterações em permissões.
- Login, logout e falhas relevantes.

Eventos de auditoria devem conter:

- Organização.
- Usuário.
- Recurso afetado.
- Ação.
- Data e hora.
- IP e user agent quando disponível.
- Diferença de campos quando aplicável.

### 8.4 Retenção e exclusão

Implementar exclusão lógica para entidades principais. Exclusão física deve ser exceção, controlada e auditada.

Prever:

- Política de retenção por tipo de documento.
- Anonimização quando houver base legal.
- Bloqueio de exclusão quando houver obrigação contratual, fiscal ou jurídica.
- Exportação de dados por organização.

## 9. Modelo de domínio inicial

### 9.1 Entidades centrais

Entidades recomendadas para a primeira modelagem:

- `Organization`
- `User`
- `OrganizationMember`
- `Client`
- `ClientContact`
- `ClientTag`
- `ServiceType`
- `ClientService`
- `Contract`
- `Task`
- `TaskChecklistItem`
- `Deadline`
- `CalendarEvent`
- `DocumentCategory`
- `Document`
- `DocumentVersion`
- `DocumentRequest`
- `DocumentRequestItem`
- `Message`
- `MessageTemplate`
- `Notification`
- `Receivable`
- `Payable`
- `Payment`
- `FinancialCategory`
- `Ticket`
- `AuditLog`

Entidades de evolução:

- `Lead`
- `Proposal`
- `AutomationRule`
- `KnowledgeBaseArticle`
- `LegalCase`
- `AccountingObligation`
- `ClientEmployee`
- `Report`
- `AiSummary`

### 9.2 Cliente

O cliente deve suportar PF e PJ sem duplicar todo o módulo.

Campos comuns:

- Organização.
- Tipo: pessoa física ou pessoa jurídica.
- Nome de exibição.
- Documento principal: CPF ou CNPJ.
- Status.
- Responsável interno principal.
- Origem.
- Prioridade.
- Risco.
- Potencial de receita.
- Observações internas.

Campos específicos podem ser separados em tabelas ou colunas opcionais, conforme complexidade:

- `client_individual_profiles`
- `client_company_profiles`

Essa separação evita uma tabela `clients` excessivamente larga e ajuda a controlar validações específicas.

### 9.3 Documentos

Documentos devem ter metadados separados do arquivo físico.

Modelo mínimo:

- `documents`: registro lógico do documento.
- `document_versions`: cada arquivo enviado/substituído.
- `document_requests`: solicitação ao cliente.
- `document_request_items`: itens específicos da solicitação.

Status recomendados:

- `requested`
- `received`
- `under_review`
- `approved`
- `rejected`
- `expired`
- `replaced`
- `cancelled`

Cada versão deve registrar:

- Nome original.
- Nome armazenado.
- Disco/bucket.
- Caminho.
- MIME type.
- Tamanho.
- Hash.
- Usuário que enviou.
- Origem: portal, interno, WhatsApp, e-mail, importação.

### 9.4 Tarefas e prazos

Tarefas representam trabalho operacional. Prazos representam compromissos temporais de maior risco.

Uma tarefa pode ter prazo, mas nem todo prazo deve ser tratado como tarefa simples. Prazos jurídicos, vencimentos documentais e obrigações contábeis podem exigir campos próprios, revisões e alertas.

Status de tarefa:

- `new`
- `in_progress`
- `waiting_client`
- `waiting_document`
- `waiting_third_party`
- `in_review`
- `completed`
- `cancelled`

Atraso deve ser calculado por data e status, não salvo manualmente como status principal.

### 9.5 Financeiro

Separar previsão, cobrança e pagamento.

Entidades:

- `receivables`: valores a receber.
- `payables`: valores a pagar.
- `payments`: baixas/pagamentos realizados.
- `payment_installments`: se parcelamento exigir granularidade própria.
- `financial_categories`: categorias e centros de custo.

Status de cobrança:

- `open`
- `paid`
- `overdue`
- `cancelled`
- `renegotiated`
- `partially_paid`

Valores monetários devem ser armazenados em centavos como inteiros ou em `decimal` com precisão definida. A equipe deve escolher um padrão único antes da primeira migration financeira.

### 9.6 Comunicação

Mensagens devem ser tratadas como registros de histórico e não apenas como disparos.

Campos essenciais:

- Cliente.
- Canal.
- Direção: enviada ou recebida.
- Remetente.
- Destinatário.
- Conteúdo.
- Status de envio.
- Status de leitura quando disponível.
- Consentimento associado quando aplicável.
- Entidade vinculada: tarefa, documento, cobrança, serviço ou chamado.

Conteúdos vindos de WhatsApp/e-mail podem exigir normalização e armazenamento de anexos como documentos ou anexos de mensagem.

## 10. Banco de dados e migrations

### 10.1 Regras gerais

- Usar migrations pequenas e com uma responsabilidade.
- Todas as tabelas de negócio devem ter `organization_id`, exceto tabelas globais controladas.
- Usar foreign keys sempre que possível.
- Indexar colunas usadas em filtros, joins e ordenação.
- Usar soft deletes nas entidades principais.
- Usar timestamps em todas as entidades mutáveis.
- Evitar JSON para dados centrais que precisam de filtro, relatório ou integridade.
- Usar JSONB apenas para metadados flexíveis e integrações.

### 10.2 Índices mínimos

Planejar índices para:

- `organization_id`
- `client_id`
- `assigned_to_user_id`
- `status`
- `due_date`
- `expires_at`
- `created_at`
- `paid_at`
- `document_number`, quando permitido e necessário
- combinações frequentes, como `(organization_id, status, due_date)`

Índices devem seguir casos reais de consulta. Não indexar indiscriminadamente.

### 10.3 Concorrência

Usar transações para:

- Conversão de lead em cliente.
- Criação de cliente com onboarding.
- Solicitação de documentos com múltiplos itens.
- Geração de cobranças recorrentes.
- Baixa de pagamento.
- Renegociação financeira.

Usar locks para:

- Processamento de webhooks de pagamento.
- Geração recorrente mensal.
- Automações que não podem duplicar tarefas/cobranças.

## 11. Filas, jobs e agendamentos

### 11.1 Jobs candidatos

- Envio de e-mail.
- Envio de WhatsApp.
- Processamento de upload e antivírus.
- Geração de thumbnails/previews.
- Alertas de vencimento de documentos.
- Alertas de tarefas e prazos.
- Geração de cobranças recorrentes.
- Processamento de webhooks.
- Geração de relatórios.
- Sincronizações externas.
- Resumos com IA.

### 11.2 Scheduler

Rotinas recorrentes:

- Marcar cobranças vencidas.
- Gerar cobranças recorrentes.
- Identificar documentos próximos do vencimento.
- Enviar lembretes pendentes.
- Consolidar indicadores diários.
- Criar obrigações mensais contábeis.
- Limpar tokens expirados e arquivos temporários.

Todas as rotinas recorrentes devem ser idempotentes.

## 12. Upload e segurança de arquivos

Regras obrigatórias:

- Validar tamanho máximo.
- Validar MIME type e extensão.
- Gerar nome interno seguro.
- Nunca confiar no nome original.
- Calcular hash do arquivo.
- Armazenar em bucket privado.
- Entregar por URL assinada com expiração curta.
- Registrar quem enviou, visualizou, aprovou, recusou e baixou.
- Impedir acesso direto por caminho previsível.

Recomendado:

- Varredura antivírus em arquivos recebidos.
- Bloqueio ou quarentena para arquivos suspeitos.
- Limites por plano e organização.
- Política de retenção por categoria.

## 13. Integração com WhatsApp e e-mail

### 13.1 WhatsApp

Usar apenas provedores oficiais ou compatíveis com WhatsApp Business Platform. Evitar soluções baseadas em automação de WhatsApp Web, pois são frágeis e arriscadas para um SaaS profissional.

Cuidados:

- Consentimento e opt-out.
- Templates aprovados quando exigido.
- Registro de eventos de entrega/leitura.
- Rate limits por provedor.
- Fallback para e-mail/notificação interna.
- Revisão humana para mensagens sensíveis, especialmente advocacia.

### 13.2 E-mail

Recomendação:

- Provedor transacional confiável.
- Domínio autenticado com SPF, DKIM e DMARC.
- Templates versionados.
- Registro de envio, falha, abertura e clique quando disponível.
- Descadastro para comunicações não obrigatórias.

## 14. Pagamentos e financeiro

### 14.1 Provedor de pagamento

Para mercado brasileiro, priorizar provedores com boleto, Pix, cartão, webhooks confiáveis e boa API.

Opções:

- Asaas.
- Iugu.
- Pagar.me.
- Mercado Pago.
- Stripe, se cartão internacional for relevante.

### 14.2 Webhooks

Webhooks devem:

- Validar assinatura ou segredo.
- Ser idempotentes.
- Registrar payload bruto com retenção adequada.
- Responder rápido e processar pesado em job.
- Tratar eventos fora de ordem.
- Não confiar apenas no status enviado; consultar API do provedor quando necessário.

### 14.3 Regras financeiras

- Nunca alterar pagamento liquidado sem trilha de auditoria.
- Cancelamento e estorno devem ser eventos explícitos.
- Relatórios devem distinguir competência, vencimento e recebimento.
- Inadimplência deve considerar tolerância configurável.
- Cobranças recorrentes devem evitar duplicidade por período.

## 15. Relatórios e indicadores

### 15.1 Estratégia inicial

Começar com consultas otimizadas no banco transacional e agregações simples.

Indicadores frequentes podem ser materializados em tabelas de resumo quando houver volume:

- Indicadores diários por organização.
- Receita mensal.
- Tarefas por status.
- Documentos por vencimento.
- Inadimplência.
- Produtividade por colaborador.

### 15.2 Regras

- Relatórios devem respeitar permissões.
- Dados financeiros não devem aparecer para perfis sem acesso.
- Relatórios do cliente devem conter apenas informações liberadas.
- Indicadores devem documentar fórmula de cálculo.

## 16. Automações

### 16.1 Abordagem inicial

Não criar um motor genérico complexo no MVP. Começar com automações bem definidas por eventos e configurações simples.

Exemplos:

- Ao criar cliente com serviço X, criar checklist Y.
- Ao solicitar documento, enviar notificação.
- Ao documento vencer em N dias, notificar responsável.
- Ao cobrança atrasar, notificar financeiro.
- Ao tarefa vencer, notificar responsável.

### 16.2 Evolução

Quando houver validação de uso, evoluir para `AutomationRule` com:

- Gatilho.
- Condições.
- Ações.
- Janela de execução.
- Limite de repetição.
- Logs de execução.

Toda automação deve ser rastreável, configurável e reversível quando possível.

## 17. Inteligência artificial

### 17.1 Usos aceitáveis

- Resumo de histórico do cliente.
- Resumo de conversas.
- Sugestão de resposta.
- Geração de rascunho de relatório.
- Classificação de prioridade.
- Detecção de risco operacional.
- Perguntas gerenciais sobre dados agregados.

### 17.2 Restrições

- Não apresentar resposta gerada como decisão jurídica, contábil ou financeira.
- Exigir revisão humana para mensagens sensíveis.
- Não treinar modelos externos com dados de clientes sem base legal e contrato adequado.
- Registrar quando conteúdo foi gerado ou sugerido por IA.
- Permitir desativar IA por organização.
- Reduzir dados enviados ao provedor ao mínimo necessário.

## 18. Padrões Laravel

### 18.1 Controllers

- Usar controllers por recurso.
- Usar route model binding.
- Autorizar com policies.
- Validar com Form Requests.
- Retornar API Resources.
- Delegar regra de negócio para Actions.

### 18.2 Models

- Definir `$fillable` ou `$guarded`.
- Usar casts no método `casts()`.
- Definir relações com tipos de retorno.
- Criar escopos locais para filtros reutilizáveis.
- Evitar lógica complexa de caso de uso dentro do model.
- Ativar prevenção de lazy loading em desenvolvimento.

### 18.3 Validação

- Usar Form Requests.
- Usar `$request->validated()`.
- Validar autorização no request ou policy, conforme caso.
- Validar arrays e uploads explicitamente.
- Usar regras customizadas para CPF/CNPJ, se necessário.

### 18.4 Consultas

- Evitar N+1 com eager loading.
- Selecionar colunas necessárias em listagens grandes.
- Usar paginação.
- Indexar filtros frequentes.
- Usar `withCount()` para contagens.
- Usar jobs/chunks para processamento grande.

### 18.5 Testes

- Testes de feature para endpoints críticos.
- Testes unitários para regras puras de domínio.
- Factories para entidades principais.
- Fakes para filas, notificações, eventos e HTTP externo.
- Testar permissões e isolamento multi-tenant.

## 19. Estrutura de diretórios sugerida

Manter a estrutura Laravel e adicionar pastas conforme necessidade:

```text
app/
  Actions/
    Clients/
    Documents/
    Finance/
  Enums/
  Http/
    Controllers/Api/V1/
    Requests/
    Resources/
  Models/
  Policies/
  Jobs/
  Events/
  Listeners/
  Notifications/
  Services/
    Payments/
    Messaging/
    Storage/
  Support/
```

Evitar criar uma arquitetura pesada antes de existir volume real. O objetivo é clareza modular, não excesso de camadas.

## 20. Observabilidade

### 20.1 Logs

Logs devem ser estruturados e incluir:

- `organization_id`
- `user_id`
- `request_id`
- ação
- recurso afetado
- provedor externo, quando houver

Nunca registrar:

- Senhas.
- Tokens.
- Conteúdo integral de documentos.
- Dados bancários completos.
- Payloads sensíveis sem mascaramento.

### 20.2 Métricas

Monitorar:

- Tempo de resposta da API.
- Taxa de erro por endpoint.
- Jobs falhando.
- Tempo de fila.
- Uso de armazenamento.
- Envios de mensagem com falha.
- Webhooks rejeitados.
- Logins falhos.
- Volume por organização.

### 20.3 Alertas

Alertas para:

- Falha recorrente em pagamentos.
- Fila parada.
- Erros 5xx elevados.
- Bucket inacessível.
- Crescimento anormal de armazenamento.
- Falha de jobs de cobrança recorrente.

## 21. Deploy e ambientes

### 21.1 Ambientes

- Local.
- Testing.
- Staging.
- Production.

Staging deve ter integrações externas em sandbox sempre que possível.

### 21.2 Pipeline

Pipeline mínimo:

- Instalar dependências.
- Rodar Pint.
- Rodar testes.
- Rodar análise estática, se adotada.
- Build de assets, quando aplicável.
- Executar migrations com estratégia controlada.
- Publicar release.
- Reiniciar workers.

### 21.3 Infraestrutura recomendada

Opção simples:

- Laravel Cloud ou plataforma gerenciada compatível.
- PostgreSQL gerenciado.
- Redis gerenciado.
- S3 compatível.
- CDN para assets públicos, não para documentos privados sem assinatura.

Opção customizada:

- Docker.
- Nginx.
- PHP-FPM.
- Supervisor/Horizon.
- PostgreSQL.
- Redis.
- Object storage.

## 22. Qualidade e governança técnica

### 22.1 Definition of Done

Uma funcionalidade só deve ser considerada pronta quando:

- Tem validação de entrada.
- Tem autorização.
- Respeita organização ativa.
- Tem testes relevantes.
- Tem auditoria se alterar dado sensível.
- Trata erros previsíveis.
- Não cria N+1 óbvio.
- Possui migrations reversíveis quando aplicável.
- Está documentada no contrato da API quando exposta.

### 22.2 Revisão de código

Pontos obrigatórios de revisão:

- Escopo multi-tenant.
- Vazamento de dados entre organizações.
- Permissões.
- Integridade financeira.
- Acesso a documentos.
- Idempotência de jobs/webhooks.
- Performance de listagens.
- Cobertura de testes.

### 22.3 Convenções de idioma

Código em inglês:

- Classes.
- Métodos.
- Enums.
- Tabelas.
- Colunas.

Interface e mensagens ao usuário em português do Brasil, com arquivos de tradução quando aplicável.

## 23. Priorização técnica do MVP

### 23.1 MVP recomendado

Módulos:

- Organizações e usuários.
- Autenticação.
- Perfis e permissões básicos.
- Clientes PF/PJ.
- Ficha do cliente.
- Documentos.
- Solicitação de documentos.
- Tarefas e prazos.
- Agenda simples.
- Financeiro básico de contas a receber.
- Modelos de mensagens.
- Histórico de comunicações manual.
- Portal simples do cliente.
- Relatórios básicos.
- Auditoria inicial.

### 23.2 Fora do MVP

Adiar:

- IA.
- Motor genérico de automações.
- CRM completo.
- Assinatura digital.
- Integração profunda com WhatsApp.
- Integração contábil/fiscal externa.
- Relatórios customizados avançados.
- Módulo jurídico processual completo.
- Módulo de departamento pessoal completo.

### 23.3 Entregas incrementais sugeridas

Fase 1:

- Base SaaS, autenticação, organizações, clientes e permissões.

Fase 2:

- Documentos, uploads, categorias, validade e solicitações.

Fase 3:

- Tarefas, prazos, agenda e notificações.

Fase 4:

- Financeiro básico e cobranças.

Fase 5:

- Portal do cliente e comunicação estruturada.

Fase 6:

- Relatórios gerenciais e indicadores.

Fase 7:

- Automações, integrações e módulos verticais.

## 24. Riscos técnicos e cuidados

### 24.1 Escopo amplo demais

O briefing descreve uma plataforma grande. O maior risco é tentar construir tudo de uma vez. A solução é modularizar e entregar valor real primeiro em cliente, documentos, tarefas e financeiro.

### 24.2 Vazamento entre organizações

Multi-tenancy por coluna exige disciplina. Todo endpoint, job, policy e query precisa respeitar organização ativa.

### 24.3 Documentos sensíveis

Upload mal protegido é risco crítico. Arquivos devem ser privados, auditados e entregues por URL temporária.

### 24.4 Financeiro inconsistente

Cobranças, pagamentos e webhooks exigem idempotência, transações e trilha de eventos. Não tratar financeiro como simples CRUD.

### 24.5 WhatsApp não oficial

Integrações não oficiais podem quebrar, banir números e prejudicar clientes. Usar provedores oficiais.

### 24.6 Automações excessivas

Automações sem revisão podem enviar mensagens inadequadas ou criar duplicidades. Começar com automações simples, rastreáveis e configuráveis.

### 24.7 Relatórios lentos

Relatórios devem nascer com filtros, índices e paginação. Agregações pesadas podem migrar para snapshots/materializações depois.

## 25. Decisões técnicas recomendadas

Decisões iniciais:

- Backend: Laravel 13.
- API: REST JSON versionada em `/api/v1`.
- Banco: PostgreSQL.
- Cache/fila: Redis.
- Autenticação: Laravel Sanctum.
- Arquivos: S3 privado ou compatível.
- Filas: Laravel Queue, com Horizon quando em produção.
- Testes: PHPUnit.
- Estilo: Laravel Pint.
- Multi-tenancy: banco compartilhado com `organization_id`.
- Autorização: Policies + papéis/permissões.
- Webhooks: idempotentes, auditados e processados por jobs.

Alternativas registradas:

- MySQL 8 no lugar de PostgreSQL se houver exigência operacional.
- SQS no lugar de Redis Queue se a infraestrutura AWS exigir.
- Meilisearch quando busca textual superar o banco.
- Banco por tenant apenas para clientes enterprise ou exigência forte de isolamento.

## 26. Próximos artefatos técnicos recomendados

Após este documento, criar:

- Mapa de entidades e relacionamentos do MVP.
- Matriz de permissões por papel.
- Contrato inicial da API.
- Backlog técnico por fase.
- Convenção de nomenclatura de status/enums.
- Plano de testes por módulo.
- Política de retenção e auditoria.
- Checklist de segurança para uploads e documentos.

