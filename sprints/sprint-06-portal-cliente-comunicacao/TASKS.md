# Sprint 06 — Portal do Cliente e Comunicação

## Objetivo

Implementar acesso externo do cliente e comunicação estruturada: modelos de mensagens, envio individual manual, histórico, consentimentos, notificações internas, chamados simples, portal para documentos, cobranças, solicitações e comunicados.

## Referências

- `docs/briefing_app_gestao_escritorios.md`
- `docs/documento_tecnico_app_gestao_escritorios.md`
- `docs/casos_de_uso_app_gestao_escritorios.md`
- Casos de uso: UC-065, UC-066, UC-068, UC-070, UC-072, UC-073, UC-075 a UC-083, UC-096, UC-152, UC-153.

## Escopo funcional

- Criar modelo de mensagem.
- Enviar mensagem individual ao cliente.
- Registrar comunicação recebida/manual.
- Controlar consentimento de comunicação.
- Gerenciar notificações internas.
- Criar chamado a partir de mensagem.
- Acessar portal do cliente.
- Visualizar documentos solicitados no portal.
- Consultar cobranças no portal.
- Abrir solicitação pelo portal.
- Acompanhar status de solicitação pelo portal.
- Confirmar reunião pelo portal.
- Consultar comunicados.
- Atualizar dados cadastrais pelo portal.
- Baixar relatório liberado ao cliente quando já existir.

## Tarefas técnicas

### Modelagem e migrations

- Criar tabela `message_templates`.
- Criar tabela `messages`.
- Criar tabela `communication_consents`.
- Criar tabela `tickets`.
- Criar tabela `ticket_messages` ou comentários de chamados.
- Criar tabela `client_portal_accesses`, se acesso do cliente for separado de `users`.
- Criar tabela `announcements` ou comunicados.
- Incluir `organization_id`, `client_id`, canal, direção, status, visibilidade e consentimento.
- Adicionar índices por organização, cliente, canal, status e datas.

### Portal do cliente

- Definir modelo de autenticação do cliente externo.
- Implementar login ou acesso por convite/link seguro conforme decisão técnica.
- Garantir isolamento estrito: cliente só acessa seus próprios dados.
- Implementar painel do portal com solicitações documentais, cobranças, chamados e comunicados.
- Implementar visualização de documentos solicitados.
- Reutilizar upload documental da Sprint 03.
- Implementar consulta de cobranças visíveis ao cliente.
- Implementar abertura e acompanhamento de solicitação/chamado.
- Implementar confirmação de reunião.
- Implementar atualização de dados cadastrais com revisão interna quando necessário.

### Comunicação

- Implementar CRUD de modelos de mensagem.
- Implementar variáveis seguras em modelos, como nome do cliente, vencimento e lista de documentos.
- Implementar envio individual manual com registro de histórico.
- Nesta sprint, envio pode ser apenas registro interno ou canal simulado, se integração externa real não estiver pronta.
- Implementar registro manual de mensagem recebida.
- Permitir vínculo de mensagem a cliente, documento, cobrança, tarefa ou chamado quando recurso existir.

### Consentimentos

- Implementar registro de consentimento por cliente, canal e finalidade.
- Implementar revogação de consentimento.
- Bloquear envio por canal quando consentimento exigido estiver ausente ou revogado.
- Registrar histórico de consentimento.

### Chamados

- Implementar criação de chamado interno e pelo portal.
- Implementar status: novo, em análise, aguardando cliente, aguardando terceiro, em execução, resolvido, encerrado.
- Permitir responsável, prioridade, prazo e anexos/documentos.
- Permitir criar chamado a partir de mensagem.
- Exibir chamados na ficha do cliente e no portal quando visíveis.

### Notificações internas

- Usar sistema de notificações para eventos básicos: documento enviado pelo cliente, chamado aberto, mensagem registrada, cobrança visualizada, reunião confirmada.
- Permitir marcar notificação como lida.

### Segurança

- Criar policies específicas para portal e cliente externo.
- Impedir exposição de dados internos, observações privadas e documentos não liberados.
- Garantir que cobranças internas não visíveis não apareçam no portal.
- Auditar acessos externos relevantes.

### Testes

- Testar acesso do cliente ao portal.
- Testar bloqueio de acesso a dados de outro cliente.
- Testar visualização e upload de documentos solicitados.
- Testar consulta de cobrança visível.
- Testar abertura e acompanhamento de chamado.
- Testar consentimento bloqueando envio.
- Testar modelo de mensagem com variáveis.
- Testar notificações internas.

## Endpoints esperados

- `GET /api/v1/message-templates`
- `POST /api/v1/message-templates`
- `PATCH /api/v1/message-templates/{template}`
- `DELETE /api/v1/message-templates/{template}`
- `POST /api/v1/messages`
- `GET /api/v1/clients/{client}/messages`
- `POST /api/v1/communication-consents`
- `PATCH /api/v1/communication-consents/{consent}/revoke`
- `GET /api/v1/notifications`
- `PATCH /api/v1/notifications/{notification}/read`
- `GET /api/v1/tickets`
- `POST /api/v1/tickets`
- `GET /api/v1/tickets/{ticket}`
- `PATCH /api/v1/tickets/{ticket}`
- `POST /api/v1/tickets/{ticket}/messages`
- `GET /api/v1/portal/me`
- `GET /api/v1/portal/dashboard`
- `GET /api/v1/portal/document-requests`
- `POST /api/v1/portal/document-request-items/{item}/upload`
- `GET /api/v1/portal/receivables`
- `GET /api/v1/portal/tickets`
- `POST /api/v1/portal/tickets`
- `PATCH /api/v1/portal/calendar-events/{event}/confirm`
- `GET /api/v1/portal/announcements`
- `PATCH /api/v1/portal/profile`

## Condições de aceite

- Cliente externo acessa apenas dados vinculados a ele.
- Portal exibe solicitações documentais, cobranças visíveis, chamados e comunicados permitidos.
- Cliente consegue enviar documento solicitado pelo portal.
- Cliente consegue abrir e acompanhar solicitação.
- Comunicação relevante fica registrada no histórico do cliente.
- Envio manual respeita consentimento quando exigido.
- Revogação de consentimento bloqueia comunicações dependentes daquele consentimento.
- Notificações internas são geradas para eventos relevantes.
- Chamados possuem status, responsável, prioridade e histórico.
- Testes cobrem portal, permissões, consentimentos e comunicação básica.

## Fora do escopo

- Integração real com WhatsApp Business Platform.
- Envio real em lote.
- Status real de entrega/leitura por provedor.
- Revisão automática de mensagens sensíveis.
- App mobile nativo.

